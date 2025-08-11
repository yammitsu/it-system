<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 本番環境用のシステム管理者アカウント
        User::create([
            'name' => 'System Administrator',
            'email' => env('ADMIN_EMAIL', 'admin@system.com'),
            'email_verified_at' => now(),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe@123456!')),
            'role' => 'system_admin',
            'status' => 'active',
            'timezone' => 'Asia/Tokyo',
            'locale' => 'ja',
            'notification_settings' => [
                'email' => true,
                'slack' => false,
                'in_app' => true,
            ],
            'preferences' => [
                'theme' => 'light',
                'language' => 'ja',
                'notifications' => true,
            ],
        ]);
    }
}