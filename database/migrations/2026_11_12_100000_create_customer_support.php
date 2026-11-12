<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['variants', 'order_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedSmallInteger('warranty_days')->nullable();
                $table->boolean('login_proof_required')->default(false);
            });
        }
        Schema::create('login_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained();
            $table->string('status', 24)->default('submitted');
            $table->string('review_note')->nullable();
            $table->timestamps();
        });
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('order_item_id')->nullable()->constrained();
            $table->string('kind', 16);
            $table->string('subject', 120);
            $table->string('status', 24)->default('submitted');
            $table->timestamps();
            $table->index(['kind', 'status']);
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('support_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('login_proof_id')->nullable()->constrained();
            $table->foreignId('support_message_id')->nullable()->constrained();
            $table->string('path');
            $table->timestamps();
        });
        Schema::table('store_notifications', fn (Blueprint $table) => $table->foreignId('support_case_id')->nullable()->constrained());
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('role', 10);
            $table->string('number', 15);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('store_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('support_case_id'));
        foreach (['support_files', 'support_messages', 'support_cases', 'login_proofs', 'whatsapp_contacts'] as $name) {
            Schema::dropIfExists($name);
        }
        foreach (['order_items', 'variants'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['warranty_days', 'login_proof_required']));
        }
    }
};
