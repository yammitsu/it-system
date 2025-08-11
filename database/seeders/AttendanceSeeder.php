<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\User;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();
        
        foreach ($students as $student) {
            // 過去30日分の出席データを生成
            for ($i = 30; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                
                // 週末は除外
                if (now()->subDays($i)->isWeekend()) {
                    continue;
                }
                
                // ランダムで出席/欠席を決定（80%の確率で出席）
                if (rand(1, 10) <= 8) {
                    Attendance::create([
                        'user_id' => $student->id,
                        'attendance_date' => $date,
                        'status' => 'present',
                        'check_in_time' => '09:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00',
                        'check_out_time' => '18:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00',
                        'study_minutes' => rand(360, 480),
                        'slack_channel_id' => 'C' . str_pad(rand(1000, 9999), 8, '0', STR_PAD_LEFT),
                        'slack_invited' => true,
                        'slack_invited_at' => now()->subDays($i)->setTime(8, 0),
                        'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                    ]);
                }
            }
            
            // 明日の出席予定（50%の確率）
            if (rand(1, 2) == 1) {
                Attendance::create([
                    'user_id' => $student->id,
                    'attendance_date' => now()->addDay()->toDateString(),
                    'status' => 'present',
                    'slack_invited' => false,
                ]);
            }
        }
    }
}