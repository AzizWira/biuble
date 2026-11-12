<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\LoginProof;
use App\Models\OrderItem;
use App\Models\SupportCase;
use App\Models\SupportFile;
use App\Services\Store;
use App\Services\SupportImages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class SupportController extends Controller
{
    public function __construct(private readonly SupportImages $images) {}

    public function index(Request $request, string $kind)
    {
        $staff = $this->isStaff($request);
        $cases = SupportCase::with(['order', 'user'])->where('kind', $kind)
            ->when(! $staff, fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByRaw("CASE WHEN status IN ('resolved', 'rejected') THEN 1 ELSE 0 END")
            ->latest('updated_at')->latest('id')->paginate(20)->withQueryString();
        $proofs = $staff && $kind === 'garansi'
            ? LoginProof::with('orderItem.order')->where('status', 'submitted')->oldest('id')
                ->paginate(20, ['*'], 'bukti')->withQueryString()
            : null;

        return view('support.index', compact('cases', 'kind', 'staff', 'proofs'));
    }

    public function create(Request $request, string $kind)
    {
        abort_unless($request->user()->role === 'reseller', 403);
        $item = null;
        if ($request->filled('item')) {
            $item = OrderItem::whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with(['order', 'loginProof'])->findOrFail($request->integer('item'));
        }
        abort_if($kind === 'garansi' && ! $item, 404);
        $blocked = $kind === 'garansi' ? $item->warrantyBlock() : null;

        return view('support.create', compact('kind', 'item', 'blocked'));
    }

    public function store(Request $request, string $kind)
    {
        abort_unless($request->user()->role === 'reseller', 403);
        $data = $request->validate([
            'item' => [$kind === 'garansi' ? 'required' : 'nullable', 'integer', 'min:1'],
            'reference' => 'nullable|string|max:80',
            'subject' => 'required|string|max:120',
            'body' => 'required|string|max:3000',
            'evidence' => ($kind === 'garansi' ? 'required|' : 'nullable|').SupportImages::RULE,
        ]);
        $case = $this->withEvidence($request->file('evidence'), function (?string $path) use ($request, $kind, $data) {
            $user = Store::lockUser($request->user()->id);
            abort_unless($user->role === 'reseller', 403);
            Store::writable($user);
            $item = null;
            $order = null;
            if (! empty($data['item'])) {
                $item = OrderItem::whereHas('order', fn ($query) => $query->where('user_id', $user->id))
                    ->with(['order', 'loginProof'])->lockForUpdate()->findOrFail($data['item']);
                $order = $item->order;
            } elseif (! empty($data['reference'])) {
                $order = Order::where('user_id', $user->id)->where('reference', trim($data['reference']))->first();
                Store::check($order !== null, 'Nomor transaksi tidak ditemukan di pesananmu.');
            }
            if ($kind === 'garansi') {
                $reason = $item->warrantyBlock();
                Store::check($reason === null, $reason ?? '');
                Store::check(! SupportCase::where('kind', 'garansi')->where('order_item_id', $item->id)
                    ->whereNotIn('status', SupportCase::CLOSED)->exists(), 'Item ini masih punya pengajuan garansi aktif.');
            }
            $case = SupportCase::create([
                'user_id' => $user->id, 'kind' => $kind, 'subject' => $data['subject'],
                'order_id' => $order?->id, 'order_item_id' => $item?->id,
            ]);
            $message = $case->messages()->create(['user_id' => $user->id, 'body' => $data['body']]);
            if ($path) $message->files()->create(['path' => $path]);

            return $case;
        });

        return redirect()->route('support.show', $case)->with('status', 'Pengajuan berhasil dikirim.');
    }

    public function show(Request $request, SupportCase $case)
    {
        $this->authorizeCase($request, $case);
        $case->load(['user', 'order', 'orderItem.loginProof.files']);
        $messages = $case->messages()->with(['user', 'files'])->paginate(30);

        return view('support.show', ['case' => $case, 'staff' => $this->isStaff($request), 'messages' => $messages]);
    }

    public function reply(Request $request, SupportCase $case)
    {
        $this->authorizeCase($request, $case);
        $data = $request->validate(['body' => 'required|string|max:3000', 'evidence' => 'nullable|'.SupportImages::RULE]);
        $this->withEvidence($request->file('evidence'), function (?string $path) use ($request, $case, $data) {
            $case = SupportCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            Store::check(! $case->isClosed(), 'Pengajuan ini sudah ditutup.');
            $message = $case->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            if ($path) $message->files()->create(['path' => $path]);
            $status = $case->status;
            if ($status === 'waiting_customer' && ! $this->isStaff($request)) $status = 'reviewing';
            $case->status = $status;
            $case->touch();
            if ($this->isStaff($request)) {
                Store::notify($case->user_id, 'Ada balasan untuk pengajuan #'.$case->id.'.', $case->order_id, $case->id);
            }
        });

        return redirect()->route('support.show', ['case' => $case, 'page' => $case->messages()->paginate(30)->lastPage()])
            ->with('status', 'Balasan dikirim.');
    }

    public function review(Request $request, SupportCase $case)
    {
        Store::staff();
        $owner = $request->user()->role === 'owner';
        $allowed = ['reviewing', 'waiting_customer', 'waiting_owner'];
        if ($owner || $case->kind === 'masalah') $allowed[] = 'resolved';
        if ($owner) $allowed[] = 'rejected';
        $data = $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'body' => 'required|string|max:3000',
        ]);
        DB::transaction(function () use ($request, $case, $data) {
            $case = SupportCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            Store::check(! $case->isClosed(), 'Pengajuan ini sudah ditutup.');
            if ($data['status'] === 'resolved' && $case->kind === 'garansi') {
                $item = OrderItem::with('loginProof')->lockForUpdate()->findOrFail($case->order_item_id);
                Store::check(! $item->login_proof_required || $item->loginProof?->status === 'accepted',
                    'Periksa dan setujui bukti login terlebih dahulu.');
                Store::check(SupportFile::whereHas('supportMessage', fn ($message) => $message->where('support_case_id', $case->id))->exists(),
                    'Minta reseller mengunggah bukti masalah sebelum menutup garansi sebagai selesai.');
            }
            $case->update(['status' => $data['status']]);
            $case->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            Store::audit('support_review', (string) $case->id);
            Store::notify($case->user_id, 'Status pengajuan #'.$case->id.' diperbarui.', $case->order_id, $case->id);
        }, 3);

        return back()->with('status', 'Status pengajuan diperbarui.');
    }

    public function uploadProof(Request $request, OrderItem $item)
    {
        abort_unless($request->user()->role === 'reseller' && $item->order->user_id === $request->user()->id, 403);
        $request->validate(['evidence' => 'required|'.SupportImages::RULE]);
        $this->withEvidence($request->file('evidence'), function (string $path) use ($request, $item) {
            $user = Store::lockUser($request->user()->id);
            abort_unless($user->role === 'reseller', 403);
            Store::writable($user);
            $item = OrderItem::with('loginProof')->lockForUpdate()->findOrFail($item->id);
            Store::check($item->login_proof_required && $item->status === 'delivered' && $item->delivered_at,
                'Bukti login tidak dapat diunggah untuk item ini.');
            $proof = $item->loginProof;
            if ($proof) {
                Store::check($proof->status === 'correction', 'Bukti login sudah dikirim.');
                $proof->update(['status' => 'submitted', 'review_note' => null]);
            } else {
                $receivedAt = now();
                Store::check($receivedAt->lte($item->loginProofDeadline()), 'Batas 24 jam unggah bukti login sudah lewat.');
                $proof = $item->loginProof()->create(['created_at' => $receivedAt]);
            }
            $proof->files()->create(['path' => $path]);
        });

        return redirect()->route('orders.show', $item->order_id)->with('status', 'Bukti login berhasil dikirim.');
    }

    public function reviewProof(Request $request, OrderItem $item)
    {
        Store::staff();
        $data = $request->validate([
            'status' => 'required|in:accepted,correction',
            'review_note' => 'required_if:status,correction|nullable|string|max:255',
        ]);
        DB::transaction(function () use ($item, $data) {
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $proof = $item->loginProof()->firstOrFail();
            Store::check($proof->status === 'submitted', 'Bukti ini sudah diperiksa.');
            Store::check($proof->files()->exists(), 'Tidak ada gambar bukti login untuk diperiksa.');
            $proof->update($data + ['review_note' => null]);
            Store::notify($item->order->user_id, $data['status'] === 'accepted'
                ? 'Bukti login diterima.' : 'Bukti login perlu diperbaiki. Buka detail pesanan untuk melihat catatan staf.', $item->order_id);
            Store::audit('login_proof_review', (string) $item->id);
        }, 3);

        return back()->with('status', 'Hasil pemeriksaan bukti login disimpan.');
    }

    public function file(Request $request, SupportFile $file)
    {
        if ($file->login_proof_id) {
            abort_unless($this->isStaff($request) || $file->loginProof->orderItem->order->user_id === $request->user()->id, 403);
        } else {
            $this->authorizeCase($request, $file->supportMessage->supportCase);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->path), 404);

        $name = 'bukti-'.$file->id.'.webp';
        if ($request->boolean('lihat')) {
            return $disk->response($file->path, $name, ['Content-Type' => 'image/webp']);
        }

        return $disk->download($file->path, $name, ['Content-Type' => 'image/webp']);
    }

    public function deleteFile(SupportFile $file)
    {
        Store::staff();
        $disk = Storage::disk('local');
        $backup = null;
        try {
            DB::transaction(function () use ($file, $disk, &$backup) {
                $proof = null;
                if ($file->login_proof_id) {
                    $item = OrderItem::whereKey($file->loginProof->order_item_id)->lockForUpdate()->firstOrFail();
                    $proof = $item->loginProof()->firstOrFail();
                } else {
                    SupportCase::whereKey($file->supportMessage->support_case_id)->lockForUpdate()->firstOrFail();
                }
                $locked = SupportFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
                if ($proof && (int) $proof->files()->max('id') === $locked->id) {
                    $proof->update(['status' => 'correction', 'review_note' => 'Bukti terakhir dihapus oleh staf. Unggah bukti pengganti.']);
                    Store::notify($item->order->user_id, 'Bukti login dihapus oleh staf dan perlu diunggah ulang.', $item->order_id);
                }
                Store::audit('support_file_deleted', (string) $locked->id);
                if ($disk->exists($locked->path)) {
                    $backup = $disk->get($locked->path);
                    abort_if($backup === null || ! $disk->delete($locked->path), 503, 'Gambar belum bisa dihapus.');
                }
                $locked->delete();
            });
        } catch (Throwable $exception) {
            if ($backup !== null && ! $disk->exists($file->path)) $disk->put($file->path, $backup);
            throw $exception;
        }

        return back()->with('status', 'Gambar bukti dihapus.');
    }

    private function authorizeCase(Request $request, SupportCase $case): void
    {
        abort_unless($this->isStaff($request) || $case->user_id === $request->user()->id, 403);
    }

    private function isStaff(Request $request): bool
    {
        return in_array($request->user()->role, ['owner', 'admin'], true);
    }

    private function withEvidence(?UploadedFile $file, Closure $action): mixed
    {
        $path = $file ? $this->images->store($file) : null;
        try {
            return DB::transaction(fn () => $action($path), 3);
        } catch (Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
