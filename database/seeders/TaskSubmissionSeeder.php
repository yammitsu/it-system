<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Models\Task;

class TaskSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();
        $teachers = User::where('role', 'teacher')->get();
        
        foreach ($students as $student) {
            // 各学生の言語に対応する課題を取得
            $tasks = Task::where('language_id', $student->language_id)
                ->where('company_id', null)
                ->orderBy('display_order')
                ->get();
            
            // ランダムな進捗率（30-90%）
            $progressRate = rand(30, 90) / 100;
            $completedCount = floor($tasks->count() * $progressRate);
            
            foreach ($tasks as $index => $task) {
                if ($index >= $completedCount) {
                    break;
                }
                
                $status = 'completed';
                $startedDaysAgo = rand(10, 30);
                $completedDaysAgo = $startedDaysAgo - rand(1, 3);
                
                // 最後の数課題は進行中
                if ($index >= $completedCount - 2 && rand(1, 2) == 1) {
                    $status = 'in_progress';
                    $completedDaysAgo = null;
                }
                
                $submission = TaskSubmission::create([
                    'user_id' => $student->id,
                    'task_id' => $task->id,
                    'evaluated_by' => $status == 'completed' ? $teachers->random()->id : null,
                    'status' => $status,
                    'first_downloaded_at' => now()->subDays($startedDaysAgo),
                    'started_at' => now()->subDays($startedDaysAgo),
                    'completed_at' => $completedDaysAgo ? now()->subDays($completedDaysAgo) : null,
                    'actual_hours' => $task->estimated_hours * 60 + rand(-60, 120),
                    'progress_comment' => $this->getRandomProgressComment($status),
                    'completion_note' => $status == 'completed' ? '課題を完了しました。' : null,
                    'rating' => $status == 'completed' ? rand(3, 5) : null,
                    'feedback' => $status == 'completed' ? $this->getRandomFeedback() : null,
                    'score' => $status == 'completed' ? rand(70, 100) : null,
                    'evaluation_comment' => $status == 'completed' ? 'よく理解できています。' : null,
                    'evaluated_at' => $status == 'completed' ? now()->subDays($completedDaysAgo - 1) : null,
                    'download_count' => rand(1, 5),
                ]);
            }
        }
    }
    
    private function getRandomProgressComment($status)
    {
        $comments = [
            'in_progress' => [
                '実装中です。',
                'エラーの解決に取り組んでいます。',
                'テストコードを書いています。',
                '要件を確認しながら進めています。',
            ],
            'completed' => [
                '無事に完了できました。',
                '理解が深まりました。',
                '良い学習になりました。',
                '次の課題も頑張ります。',
            ],
        ];
        
        return $comments[$status][array_rand($comments[$status])];
    }
    
    private function getRandomFeedback()
    {
        $feedback = [
            'とても良くできています。コードも綺麗で読みやすいです。',
            '基本的な要件は満たしています。エラー処理をもう少し充実させると良いでしょう。',
            '素晴らしい実装です。効率的なアルゴリズムを使用していますね。',
            '良い取り組みです。コメントをもう少し追加すると、より理解しやすくなります。',
            '要件を正確に理解し、適切に実装できています。',
        ];
        
        return $feedback[array_rand($feedback)];
    }
}