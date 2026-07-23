<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Create a tenant so multi-tenant scoping works correctly.
        // All staff users created via /staff will inherit this tenant_id.
        $tenant = Tenant::create(['name' => 'Demo Event Co.']);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'tenant_id' => $tenant->id,
        ])->assignRole('Admin');
    }
}
