<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // マスタデータのシード
        $this->call([
            LanguageSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        // 開発環境用のテストデータ
        if (app()->environment('local', 'development')) {
            $this->call([
                CompanySeeder::class,
                UserSeeder::class,
                TaskSeeder::class,
                AttendanceSeeder::class,
                ShiftSeeder::class,
                TaskSubmissionSeeder::class,
                NotificationSeeder::class,
            ]);
        }

        // 本番環境用の初期データ
        if (app()->environment('production')) {
            $this->call([
                AdminUserSeeder::class,
            ]);
        }
    }
}