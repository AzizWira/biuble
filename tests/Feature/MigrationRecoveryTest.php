<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRecoveryTest extends TestCase
{
    public function test_interrupted_store_migration_can_resume_without_losing_data(): void
    {
        $original = config('database.default');
        config(['database.connections.migration_recovery' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ], 'database.default' => 'migration_recovery']);

        try {
            $users = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            $users->up();
            Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('reseller'));
            Schema::create('categories', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('color')->default('#def3f3');
                $t->string('image')->nullable(); $t->timestamps();
            });
            DB::table('users')->insert(['name' => 'Existing owner', 'email' => 'owner@example.test',
                'password' => 'existing-hash', 'role' => 'owner']);
            DB::table('categories')->insert(['name' => 'Existing category']);

            $migration = require database_path('migrations/2026_11_05_100000_create_store.php');
            $migration->up();
            $migration->up();

            $this->assertSame('owner', DB::table('users')->value('role'));
            $this->assertSame('Existing category', DB::table('categories')->value('name'));
            $this->assertTrue(Schema::hasIndex('users', ['phone'], 'unique'));
            $this->assertTrue(Schema::hasIndex('users', ['google_id'], 'unique'));
            foreach (['orders', 'order_items', 'wallet_entries', 'topups', 'withdrawals',
                'payments', 'store_settings', 'store_notifications', 'store_audits'] as $table) {
                $this->assertTrue(Schema::hasTable($table), $table);
            }
        } finally {
            DB::purge('migration_recovery');
            config(['database.default' => $original]);
        }
    }
}
