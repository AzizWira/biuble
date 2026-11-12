<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users','account_status')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('account_status',20)->default('active'));
        }
        if (! Schema::hasColumn('users','account_status_reason')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('account_status_reason')->nullable());
        }
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['account_status','account_status_reason']));
    }
};
