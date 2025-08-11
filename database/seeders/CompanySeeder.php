<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => '株式会社テックイノベーション',
                'code' => 'TI2024001',
                'email' => 'admin@tech-innovation.co.jp',
                'phone' => '03-1234-5678',
                'address' => '東京都千代田区丸の内1-1-1',
                'postal_code' => '100-0005',
                'representative_name' => '山田太郎',
                'slack_workspace_id' => 'T01234567',
                'slack_workspace_name' => 'tech-innovation',
                'max_users' => 100,
                'contract_start_date' => '2024-01-01',
                'contract_end_date' => '2025-12-31',
                'status' => 'active',
                'settings' => [
                    'allow_custom_tasks' => true,
                    'notification_enabled' => true,
                    'theme' => 'default',
                ],
                'notes' => '大手IT企業。新入社員研修プログラムで利用。',
            ],
            [
                'name' => '株式会社デジタルソリューション',
                'code' => 'DS2024002',
                'email' => 'training@digital-solution.co.jp',
                'phone' => '06-9876-5432',
                'address' => '大阪府大阪市北区梅田2-2-2',
                'postal_code' => '530-0001',
                'representative_name' => '佐藤花子',
                'slack_workspace_id' => 'T98765432',
                'slack_workspace_name' => 'digital-solution',
                'max_users' => 50,
                'contract_start_date' => '2024-04-01',
                'contract_end_date' => '2025-03-31',
                'status' => 'active',
                'settings' => [
                    'allow_custom_tasks' => false,
                    'notification_enabled' => true,
                    'theme' => 'dark',
                ],
                'notes' => '中堅IT企業。スキルアップ研修で利用。',
            ],
            [
                'name' => 'スタートアップABC株式会社',
                'code' => 'SA2024003',
                'email' => 'hr@startup-abc.com',
                'phone' => '052-1111-2222',
                'address' => '愛知県名古屋市中区栄3-3-3',
                'postal_code' => '460-0008',
                'representative_name' => '鈴木一郎',
                'slack_workspace_id' => 'T11223344',
                'slack_workspace_name' => 'startup-abc',
                'max_users' => 20,
                'contract_start_date' => '2024-06-01',
                'contract_end_date' => '2025-05-31',
                'status' => 'active',
                'settings' => [
                    'allow_custom_tasks' => true,
                    'notification_enabled' => true,
                    'theme' => 'light',
                ],
                'notes' => 'スタートアップ企業。エンジニア育成プログラムで利用。',
            ],
            [
                'name' => 'グローバルテック株式会社',
                'code' => 'GT2024004',
                'email' => 'education@global-tech.co.jp',
                'phone' => '092-3333-4444',
                'address' => '福岡県福岡市博多区博多駅前4-4-4',
                'postal_code' => '812-0011',
                'representative_name' => '田中美咲',
                'slack_workspace_id' => 'T55667788',
                'slack_workspace_name' => 'global-tech',
                'max_users' => 150,
                'contract_start_date' => '2024-02-01',
                'contract_end_date' => '2026-01-31',
                'status' => 'active',
                'settings' => [
                    'allow_custom_tasks' => true,
                    'notification_enabled' => true,
                    'theme' => 'default',
                    'multi_language' => true,
                ],
                'notes' => '外資系IT企業。グローバル人材育成プログラムで利用。',
            ],
            [
                'name' => 'システム開発センター株式会社',
                'code' => 'SDC2024005',
                'email' => 'training@sdc.co.jp',
                'phone' => '011-5555-6666',
                'address' => '北海道札幌市中央区大通西5-5-5',
                'postal_code' => '060-0042',
                'representative_name' => '高橋健太',
                'slack_workspace_id' => '',
                'slack_workspace_name' => '',
                'max_users' => 30,
                'contract_start_date' => '2024-07-01',
                'contract_end_date' => '2024-12-31',
                'status' => 'active',
                'settings' => [
                    'allow_custom_tasks' => false,
                    'notification_enabled' => false,
                    'theme' => 'default',
                ],
                'notes' => '地方IT企業。試験導入中。Slack未連携。',
            ],
        ];

        foreach ($companies as $company) {
            Company::create($company);
        }
    }
}