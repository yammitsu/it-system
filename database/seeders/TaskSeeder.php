<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Language;
use App\Models\User;
use App\Models\Company;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = Language::all();
        $teachers = User::where('role', 'teacher')->get();
        $companies = Company::all();

        foreach ($languages as $language) {
            $this->createTasksForLanguage($language, $teachers, $companies);
        }
    }

    /**
     * 言語ごとの課題を作成
     */
    private function createTasksForLanguage($language, $teachers, $companies)
    {
        $taskCategories = $this->getTaskCategories($language->code);
        $teacher = $teachers->random();
        $order = 0;

        foreach ($taskCategories as $major => $minors) {
            foreach ($minors as $minorIndex => $minor) {
                $order++;
                $difficulty = $this->getDifficultyByOrder($order);
                
                // 共通課題
                $task = Task::create([
                    'language_id' => $language->id,
                    'company_id' => null,
                    'created_by' => $teacher->id,
                    'parent_task_id' => null,
                    'category_major' => $major,
                    'category_minor' => $minor['name'],
                    'title' => $minor['title'],
                    'description' => $minor['description'],
                    'instructions' => $this->getInstructions($language->code, $minor['name']),
                    'prerequisites' => $minor['prerequisites'],
                    'file_path' => 'tasks/' . $language->code . '/' . $order . '/sample.zip',
                    'file_name' => $minor['name'] . '_sample.zip',
                    'file_size' => rand(1024, 10485760),
                    'file_mime_type' => 'application/zip',
                    'file_scanned_at' => now(),
                    'file_is_safe' => true,
                    'estimated_hours' => $minor['hours'],
                    'points' => $minor['points'],
                    'difficulty' => $difficulty,
                    'display_order' => $order,
                    'is_required' => $minor['required'],
                    'is_template' => false,
                    'is_active' => true,
                    'available_from' => now()->subDays(30),
                    'available_until' => now()->addYears(1),
                    'tags' => $minor['tags'],
                    'metadata' => [
                        'version' => '1.0',
                        'updated_count' => 0,
                    ],
                    'completion_count' => rand(0, 50),
                    'average_completion_time' => $minor['hours'] + rand(-2, 2),
                    'average_rating' => rand(35, 50) / 10,
                ]);

                // 企業専用課題（一部の企業のみ）
                if ($minorIndex % 3 == 0) {
                    $company = $companies->random();
                    Task::create([
                        'language_id' => $language->id,
                        'company_id' => $company->id,
                        'created_by' => $teacher->id,
                        'parent_task_id' => $task->id,
                        'category_major' => $major,
                        'category_minor' => $minor['name'] . ' (カスタム)',
                        'title' => $minor['title'] . ' - ' . $company->name . '専用',
                        'description' => $minor['description'] . "\n\n【企業専用課題】" . $company->name . "の業務に即した内容です。",
                        'instructions' => $this->getInstructions($language->code, $minor['name']),
                        'prerequisites' => $minor['prerequisites'],
                        'estimated_hours' => $minor['hours'] + 1,
                        'points' => $minor['points'] + 5,
                        'difficulty' => $difficulty,
                        'display_order' => $order,
                        'is_required' => false,
                        'is_template' => false,
                        'is_active' => true,
                        'tags' => array_merge($minor['tags'], ['企業専用']),
                    ]);
                }
            }
        }
    }

    /**
     * 言語別の課題カテゴリを取得
     */
    private function getTaskCategories($languageCode)
    {
        $baseCategories = [
            '基礎編' => [
                ['name' => '環境構築', 'title' => '開発環境の構築', 'description' => '開発に必要な環境を構築します', 'prerequisites' => 'PCの基本操作', 'hours' => 2, 'points' => 10, 'required' => true, 'tags' => ['環境構築', '初級']],
                ['name' => '基本文法', 'title' => '基本文法の学習', 'description' => '言語の基本的な文法を学びます', 'prerequisites' => '環境構築完了', 'hours' => 4, 'points' => 20, 'required' => true, 'tags' => ['文法', '初級']],
                ['name' => '変数と型', 'title' => '変数と型の理解', 'description' => '変数の宣言と型について学びます', 'prerequisites' => '基本文法の理解', 'hours' => 3, 'points' => 15, 'required' => true, 'tags' => ['変数', '型', '初級']],
                ['name' => '制御構造', 'title' => '制御構造の実装', 'description' => 'if文やループ処理を学びます', 'prerequisites' => '変数と型の理解', 'hours' => 4, 'points' => 20, 'required' => true, 'tags' => ['制御構造', '初級']],
                ['name' => '関数', 'title' => '関数の作成と利用', 'description' => '関数の定義と呼び出しを学びます', 'prerequisites' => '制御構造の理解', 'hours' => 5, 'points' => 25, 'required' => true, 'tags' => ['関数', '初級']],
            ],
            '応用編' => [
                ['name' => 'オブジェクト指向', 'title' => 'オブジェクト指向プログラミング', 'description' => 'クラスとオブジェクトについて学びます', 'prerequisites' => '関数の理解', 'hours' => 8, 'points' => 40, 'required' => false, 'tags' => ['OOP', '中級']],
                ['name' => 'エラー処理', 'title' => 'エラー処理の実装', 'description' => '例外処理とエラーハンドリングを学びます', 'prerequisites' => 'オブジェクト指向の理解', 'hours' => 4, 'points' => 20, 'required' => false, 'tags' => ['エラー処理', '中級']],
                ['name' => 'ファイル操作', 'title' => 'ファイル入出力', 'description' => 'ファイルの読み書きを学びます', 'prerequisites' => 'エラー処理の理解', 'hours' => 5, 'points' => 25, 'required' => false, 'tags' => ['ファイル', '中級']],
                ['name' => 'データベース', 'title' => 'データベース連携', 'description' => 'データベースとの接続と操作を学びます', 'prerequisites' => 'ファイル操作の理解', 'hours' => 10, 'points' => 50, 'required' => false, 'tags' => ['DB', '中級']],
            ],
            '実践編' => [
                ['name' => 'Webアプリ', 'title' => 'Webアプリケーション開発', 'description' => '簡単なWebアプリを開発します', 'prerequisites' => 'データベースの理解', 'hours' => 20, 'points' => 100, 'required' => false, 'tags' => ['Web', '上級']],
                ['name' => 'API開発', 'title' => 'REST API開発', 'description' => 'RESTfulなAPIを開発します', 'prerequisites' => 'Webアプリの理解', 'hours' => 15, 'points' => 75, 'required' => false, 'tags' => ['API', '上級']],
                ['name' => 'テスト', 'title' => 'テストコードの作成', 'description' => 'ユニットテストを書きます', 'prerequisites' => 'API開発の理解', 'hours' => 8, 'points' => 40, 'required' => false, 'tags' => ['テスト', '上級']],
                ['name' => 'デプロイ', 'title' => 'アプリケーションのデプロイ', 'description' => '本番環境へのデプロイを学びます', 'prerequisites' => 'テストの理解', 'hours' => 6, 'points' => 30, 'required' => false, 'tags' => ['デプロイ', '上級']],
            ],
        ];

        return $baseCategories;
    }

    /**
     * 順番による難易度を取得
     */
    private function getDifficultyByOrder($order)
    {
        if ($order <= 5) return 'beginner';
        if ($order <= 9) return 'intermediate';
        if ($order <= 12) return 'advanced';
        return 'expert';
    }

    /**
     * 実施手順を生成
     */
    private function getInstructions($languageCode, $taskType)
    {
        return "1. 課題ファイルをダウンロードしてください\n"
            . "2. ファイルを解凍し、READMEを確認してください\n"
            . "3. 指定された要件に従って実装してください\n"
            . "4. テストを実行し、全てパスすることを確認してください\n"
            . "5. 完了したら「完了」ボタンを押してください";
    }
}