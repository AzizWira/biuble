<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->string('name', 400)->change());
    }
    public function down(): void
    {
        // Historical order labels retain their full length.
    }
};

