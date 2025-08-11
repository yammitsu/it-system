<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;
use App\Models\User;
use App\Models\Company;
use App\Models\Language;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->get();
        $companies = Company::all();
        $languages = Language::all();
        
        foreach ($teachers as $teacher) {
            // 今後30日分のシフトを生成
            for ($i = 0; $i <= 30; $i++) {
                $date = now()->addDays($i)->toDateString();
                
                // 週末は除外
                if (now()->addDays($i)->isWeekend()) {
                    continue;
                }
                
                Shift::create([
                    'teacher_id' => $teacher->id,
                    'company_id' => $companies->random()->id,
                    'language_id' => $languages->random()->id,
                    'shift_date' => $date,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'status' => $i == 0 ? 'confirmed' : 'scheduled',
                    'slack_channel_id' => $i == 0 ? 'C' . str_pad(rand(1000, 9999), 8, '0', STR_PAD_LEFT) : null,
                    'slack_channel_created' => $i == 0,
                    'max_students' => rand(15, 25),
                    'current_students' => $i == 0 ? rand(5, 15) : 0,
                    'notes' => $i == 0 ? '本日の講習' : null,
                ]);
            }
        }
    }
}