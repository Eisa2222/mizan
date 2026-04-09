<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['domain' => 'mizaan.local'],
            ['name_ar' => 'ميزان - افتراضية', 'name_en' => 'Mizaan Default']
        );

        User::firstOrCreate(
            ['email' => 'admin@mizaan.local'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('Admin@123'),
                'org_id' => $org->id,
                'role' => 'SuperAdmin',
                'email_verified_at' => now(),
            ]
        );
    }
}
