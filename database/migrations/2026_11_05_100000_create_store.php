<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL commits immediately, so an interrupted migration may leave
        // columns and tables behind even though its migration record is absent.
        foreach (['role', 'approval', 'phone', 'google_id'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $t) use ($column) {
                    match ($column) {
                        'role' => $t->string($column)->default('reseller'),
                        'approval' => $t->string($column)->default('draft'),
                        default => $t->string($column)->nullable(),
                    };
                });
            }
        }
        foreach (['phone', 'google_id'] as $column) {
            if (! Schema::hasIndex('users', [$column], 'unique')) {
                Schema::table('users', fn (Blueprint $t) => $t->unique($column));
            }
        }
        $this->createIfMissing('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('color')->default('#def3f3');
            $t->string('image')->nullable(); $t->timestamps();
        });
        $this->createIfMissing('products', function (Blueprint $t) {
            $t->id(); $t->foreignId('category_id')->constrained();
            $t->string('name'); $t->string('slug')->unique(); $t->text('description');
            $t->string('color')->default('#def3f3'); $t->string('image')->nullable();
            $t->boolean('active')->default(true); $t->timestamps();
        });
        $this->createIfMissing('variants', function (Blueprint $t) {
            $t->id(); $t->foreignId('product_id')->constrained();
            $t->string('name'); $t->string('type')->default('sharing');
            $t->string('delivery')->default('stock'); $t->unsignedBigInteger('price');
            $t->unsignedInteger('duration_days')->nullable();
            $t->boolean('designer')->default(false); $t->boolean('active')->default(true);
            $t->unsignedInteger('discount_percent')->default(0);
            $t->timestamps();
        });
        $this->createIfMissing('stock_accounts', function (Blueprint $t) {
            $t->id(); $t->foreignId('variant_id')->constrained();
            $t->text('credentials'); $t->timestamps();
        });
        $this->createIfMissing('stock_slots', function (Blueprint $t) {
            $t->id(); $t->foreignId('stock_account_id')->constrained();
            $t->string('label'); $t->string('state')->default('available'); $t->uuid('reservation_token')->nullable();
            $t->timestamps();
        });
        $this->createIfMissing('orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->uuid('token')->unique();
            $t->string('reference')->unique(); $t->string('method');
            $t->string('status')->default('pending'); $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('fee')->default(0); $t->unsignedBigInteger('total');
            $t->timestamp('paid_at')->nullable(); $t->dateTime('expires_at');
            $t->string('phone'); $t->timestamps();
        });
        $this->createIfMissing('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained();
            $t->foreignId('variant_id')->constrained();
            $t->foreignId('stock_slot_id')->nullable()->constrained();
            $t->string('name'); $t->string('delivery'); $t->string('status')->default('reserved');
            $t->unsignedBigInteger('amount'); $t->unsignedInteger('duration_days')->nullable();
            $t->boolean('designer')->default(false); $t->string('email')->nullable();
            $t->text('access')->nullable(); $t->timestamp('delivered_at')->nullable();
            $t->timestamp('ends_at')->nullable(); $t->timestamps();
        });
        $this->createIfMissing('wallet_entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->string('source');
            $t->bigInteger('amount'); $t->string('key')->unique();
            $t->string('note'); $t->timestamps();
        });
        $this->createIfMissing('topups', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->uuid('token')->unique();
            $t->string('reference')->unique(); $t->string('method');
            $t->unsignedBigInteger('amount'); $t->unsignedBigInteger('fee')->default(0);
            $t->unsignedBigInteger('total'); $t->string('status')->default('pending');
            $t->string('proof')->nullable(); $t->timestamps();
        });
        $this->createIfMissing('withdrawals', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->uuid('token')->unique();
            $t->unsignedBigInteger('amount'); $t->unsignedBigInteger('fee');
            $t->unsignedBigInteger('net'); $t->json('allocation');
            $t->string('destination'); $t->string('status')->default('pending');
            $t->string('transfer_reference')->nullable(); $t->timestamps();
        });
        $this->createIfMissing('payments', function (Blueprint $t) {
            $t->id(); $t->string('reference')->unique(); $t->string('kind');
            $t->unsignedBigInteger('target_id'); $t->unsignedBigInteger('amount');
            $t->string('status')->default('pending'); $t->text('url')->nullable();
            $t->uuid('request_id')->unique(); $t->timestamps();
        });
        $this->createIfMissing('store_settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->text('value');
        });
        $this->createIfMissing('store_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained();
            $t->string('message'); $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamp('created_at');
        });
        $this->createIfMissing('store_audits', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action'); $t->string('subject'); $t->timestamp('created_at');
        });
    }

    private function createIfMissing(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    public function down(): void
    {
        foreach (['store_audits', 'store_notifications', 'store_settings', 'payments',
            'withdrawals', 'topups', 'wallet_entries', 'order_items', 'orders',
            'stock_slots', 'stock_accounts', 'variants', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'approval', 'phone', 'google_id']));
    }
};


