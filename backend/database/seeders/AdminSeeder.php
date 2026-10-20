<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Create (or update) the single professor account from ADMIN_USERNAME / ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $username = config('admin.username');
        $password = config('admin.password');

        if (blank($username) || blank($password)) {
            throw new RuntimeException('ADMIN_USERNAME et ADMIN_PASSWORD doivent être définis dans .env');
        }

        User::updateOrCreate(['username' => $username], ['password' => $password]);
    }
}
