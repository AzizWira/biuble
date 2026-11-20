<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['categories', 'products', 'variants', 'stock_accounts', 'stock_slots'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->softDeletes());
        }
        Schema::table('products', function (Blueprint $t) {
            $t->boolean('login_proof_required')->default(false);
            $t->string('stock_mode', 16)->default('slots');
        });
        // Preserve the existing requirement for applications that already requested proof.
        DB::table('products')->whereIn('id', DB::table('variants')->where('login_proof_required', true)->select('product_id'))
            ->update(['login_proof_required' => true]);
        Schema::table('stock_slots', fn (Blueprint $t) => $t->text('pin')->nullable());
        Schema::table('login_proofs', function (Blueprint $t) {
            $t->string('device', 150)->nullable();
            $t->string('location', 150)->nullable();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->boolean('login_details_required')->default(false));
        Schema::table('payments', fn (Blueprint $t) => $t->string('method')->nullable());
        Schema::table('store_notifications', fn (Blueprint $t) => $t->timestamp('read_at')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('activity_read_id')->default(0));
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('title', 150);
            $t->text('body');
            $t->boolean('published')->default(true);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->unsignedInteger('revision');
            $t->timestamps();
            $t->unique(['announcement_id', 'user_id']);
        });
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('variant_id')->nullable()->constrained();
            $t->string('title', 150);
            $t->unsignedTinyInteger('discount_percent');
            $t->boolean('active')->default(true);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('activity_read_id'));
        Schema::table('store_notifications', fn (Blueprint $t) => $t->dropColumn('read_at'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('method'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('login_details_required'));
        Schema::table('login_proofs', fn (Blueprint $t) => $t->dropColumn(['device', 'location']));
        Schema::table('stock_slots', fn (Blueprint $t) => $t->dropColumn('pin'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['stock_mode', 'login_proof_required']));
        foreach (['stock_slots', 'stock_accounts', 'variants', 'products', 'categories'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
