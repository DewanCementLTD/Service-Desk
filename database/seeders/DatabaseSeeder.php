<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: roles and the organisation tree must exist before users,
     * and users before any demo transactions.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            LookupSeeder::class,
            // Runs straight after the lookups so a fresh install starts with the
            // trimmed category list rather than the full shipped set.
            LookupTrimSeeder::class,
            OrganizationSeeder::class,
            SlaPolicySeeder::class,
            UserSeeder::class,
        ]);
    }
}
