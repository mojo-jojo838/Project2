<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (env('ADMIN_USERNAME') && env('ADMIN_PASSWORD')) {
            User::updateOrCreate(
                ['username' => env('ADMIN_USERNAME')],
                [
                    'name' => env('ADMIN_NAME', 'Gym Admin'),
                    'password' => Hash::make(env('ADMIN_PASSWORD')),
                    'role' => 'admin',
                    'approval_status' => 'approved',
                ]
            );
        }
    }
}
