<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Language;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $languages = Language::all();

        // システム管理者
        User::create([
            'name' => 'システム管理者',
            'email' => 'admin@system.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Admin@123456'),
            'role' => 'system_admin',
            'status' => 'active',
            'timezone' => 'Asia/Tokyo',
            'locale' => 'ja',
        ]);

        // 各企業の管理者・講師・受講生を作成
        foreach ($companies as $index => $company) {
            // 企業管理者
            User::create([
                'company_id' => $company->id,
                'name' => $company->name . ' 管理者',
                'email' => 'admin@company' . ($index + 1) . '.com',
                'email_verified_at' => now(),
                'password' => Hash::make('Password@123'),
                'slack_email' => 'admin@company' . ($index + 1) . '.com',
                'role' => 'company_admin',
                'employee_number' => 'EMP' . str_pad($index * 100 + 1, 6, '0', STR_PAD_LEFT),
                'department' => '管理部',
                'position' => '部長',
                'status' => 'active',
                'timezone' => 'Asia/Tokyo',
                'locale' => 'ja',
            ]);

            // 講師（各企業2名）
            for ($i = 1; $i <= 2; $i++) {
                User::create([
                    'company_id' => $company->id,
                    'language_id' => $languages->random()->id,
                    'name' => $company->name . ' 講師' . $i,
                    'email' => 'teacher' . $i . '@company' . ($index + 1) . '.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('Password@123'),
                    'slack_email' => 'teacher' . $i . '@company' . ($index + 1) . '.com',
                    'slack_user_id' => 'U' . str_pad(($index * 100) + ($i * 10), 8, '0', STR_PAD_LEFT),
                    'role' => 'teacher',
                    'employee_number' => 'EMP' . str_pad($index * 100 + 10 + $i, 6, '0', STR_PAD_LEFT),
                    'department' => '教育部',
                    'position' => '講師',
                    'phone' => '090-' . str_pad($index * 1000 + $i * 100, 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'timezone' => 'Asia/Tokyo',
                    'locale' => 'ja',
                ]);
            }

            // 受講生（各企業10名）
            $studentNames = [
                '田中太郎', '山田花子', '佐藤次郎', '鈴木美咲', '高橋健太',
                '渡辺真一', '伊藤優子', '中村大輔', '小林愛', '加藤翔太'
            ];

            foreach ($studentNames as $j => $name) {
                $studentNum = $j + 1;
                User::create([
                    'company_id' => $company->id,
                    'language_id' => $languages->random()->id,
                    'name' => $name . ' (' . $company->name . ')',
                    'email' => 'student' . $studentNum . '@company' . ($index + 1) . '.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('Password@123'),
                    'slack_email' => 'student' . $studentNum . '@company' . ($index + 1) . '.com',
                    'slack_user_id' => 'U' . str_pad(($index * 1000) + ($studentNum * 10), 8, '0', STR_PAD_LEFT),
                    'role' => 'student',
                    'employee_number' => 'EMP' . str_pad($index * 100 + 20 + $studentNum, 6, '0', STR_PAD_LEFT),
                    'department' => $this->getRandomDepartment(),
                    'position' => $this->getRandomPosition(),
                    'phone' => '080-' . str_pad($index * 1000 + $studentNum * 10, 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'enrollment_date' => now()->subDays(rand(1, 90)),
                    'status' => $this->getRandomStatus(),
                    'total_study_hours' => rand(0, 500) * 60, // 分単位
                    'consecutive_days' => rand(0, 30),
                    'last_login_at' => now()->subDays(rand(0, 7)),
                    'notification_settings' => [
                        'email' => true,
                        'slack' => true,
                        'in_app' => true,
                    ],
                    'preferences' => [
                        'theme' => 'light',
                        'language' => 'ja',
                        'notifications' => true,
                    ],
                    'timezone' => 'Asia/Tokyo',
                    'locale' => 'ja',
                ]);
            }
        }
    }

    /**
     * ランダムな部署を取得
     */
    private function getRandomDepartment(): string
    {
        $departments = [
            '開発部', 'システム部', 'インフラ部', '品質管理部',
            'プロジェクト管理部', '研究開発部', 'IT戦略部', 'DX推進部'
        ];
        return $departments[array_rand($departments)];
    }

    /**
     * ランダムな役職を取得
     */
    private function getRandomPosition(): string
    {
        $positions = [
            '一般社員', '主任', 'リーダー', 'サブリーダー',
            'エンジニア', 'プログラマー', 'システムエンジニア', 'コンサルタント'
        ];
        return $positions[array_rand($positions)];
    }

    /**
     * ランダムなステータスを取得
     */
    private function getRandomStatus(): string
    {
        $statuses = ['active', 'active', 'active', 'active', 'inactive'];
        return $statuses[array_rand($statuses)];
    }
}