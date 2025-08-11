<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $systemAdmin = User::where('role', 'system_admin')->first();
        
        foreach ($users as $user) {
            // システム通知
            Notification::create([
                'user_id' => $user->id,
                'sender_id' => $systemAdmin->id,
                'type' => 'system',
                'channel' => 'in_app',
                'title' => 'システムメンテナンスのお知らせ',
                'message' => '来週の月曜日、午前2時から4時までシステムメンテナンスを実施します。',
                'priority' => 'normal',
                'is_read' => rand(0, 1),
                'read_at' => rand(0, 1) ? now()->subDays(rand(1, 5)) : null,
                'is_sent' => true,
                'sent_at' => now()->subDays(7),
            ]);
            
            // 受講生向けの通知
            if ($user->role === 'student') {
                // 課題完了通知
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'task_completed',
                    'channel' => 'in_app',
                    'title' => '課題完了おめでとうございます！',
                    'message' => '「基本文法の学習」を完了しました。次の課題に進みましょう。',
                    'action_url' => '/student/tasks',
                    'priority' => 'normal',
                    'is_read' => rand(0, 1),
                    'read_at' => rand(0, 1) ? now()->subDays(rand(1, 3)) : null,
                    'is_sent' => true,
                    'sent_at' => now()->subDays(rand(1, 5)),
                ]);
                
                // 出席リマインダー
                if (rand(1, 3) == 1) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'attendance_reminder',
                        'channel' => 'email',
                        'title' => '明日の出席登録をお忘れなく',
                        'message' => '明日の講習の出席登録がまだ完了していません。',
                        'action_url' => '/student/dashboard',
                        'priority' => 'high',
                        'is_read' => false,
                        'is_sent' => true,
                        'sent_at' => now()->subHours(2),
                    ]);
                }
            }
            
            // 講師向けの通知
            if ($user->role === 'teacher') {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'shift_assigned',
                    'channel' => 'in_app',
                    'title' => 'シフトが確定しました',
                    'message' => '来週のシフトが確定しました。確認をお願いします。',
                    'action_url' => '/teacher/shifts',
                    'priority' => 'normal',
                    'is_read' => rand(0, 1),
                    'read_at' => rand(0, 1) ? now()->subDays(rand(1, 2)) : null,
                    'is_sent' => true,
                    'sent_at' => now()->subDays(3),
                ]);
            }
        }
    }
}