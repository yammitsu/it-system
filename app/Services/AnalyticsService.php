<?php

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Attendance;
use App\Models\Language;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * 企業全体の分析データ取得
     */
    public function getCompanyAnalytics(Company $company, Carbon $startDate = null, Carbon $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->subMonths(3);
        $endDate = $endDate ?? Carbon::now();

        return [
            'overview' => $this->getCompanyOverview($company),
            'progress_trends' => $this->getProgressTrends($company, $startDate, $endDate),
            'attendance_patterns' => $this->getAttendancePatterns($company, $startDate, $endDate),
            'language_distribution' => $this->getLanguageDistribution($company),
            'task_performance' => $this->getTaskPerformance($company),
            'student_rankings' => $this->getStudentRankings($company),
            'completion_forecast' => $this->getCompletionForecast($company),
            'risk_analysis' => $this->getRiskAnalysis($company),
        ];
    }

    /**
     * 個人の学習分析データ取得
     */
    public function getStudentAnalytics(User $student, Carbon $startDate = null, Carbon $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->subMonths(1);
        $endDate = $endDate ?? Carbon::now();

        return [
            'learning_curve' => $this->getLearningCurve($student, $startDate, $endDate),
            'skill_radar' => $this->getSkillRadar($student),
            'time_distribution' => $this->getTimeDistribution($student, $startDate, $endDate),
            'performance_comparison' => $this->getPerformanceComparison($student),
            'strengths_weaknesses' => $this->getStrengthsWeaknesses($student),
            'predicted_completion' => $this->getPredictedCompletion($student),
        ];
    }

    /**
     * 企業概要データ
     */
    private function getCompanyOverview(Company $company)
    {
        $totalStudents = $company->students()->count();
        $activeStudents = $company->students()->where('status', 'active')->count();
        
        $totalTasks = Task::where(function ($q) use ($company) {
            $q->whereNull('company_id')->orWhere('company_id', $company->id);
        })->count();

        $completionData = DB::table('task_submissions')
            ->join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->selectRaw('
                COUNT(DISTINCT task_submissions.user_id) as students_with_progress,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_tasks,
                AVG(CASE WHEN status = "completed" THEN score END) as average_score
            ')
            ->first();

        $avgCompletionRate = $totalStudents > 0 && $totalTasks > 0
            ? ($completionData->completed_tasks / ($totalStudents * $totalTasks)) * 100
            : 0;

        return [
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'total_tasks' => $totalTasks,
            'average_completion_rate' => round($avgCompletionRate, 1),
            'average_score' => round($completionData->average_score ?? 0, 1),
            'students_with_progress' => $completionData->students_with_progress ?? 0,
        ];
    }

    /**
     * 進捗トレンド分析
     */
    private function getProgressTrends(Company $company, Carbon $startDate, Carbon $endDate)
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $weekStart = $current->copy()->startOfWeek();
            $weekEnd = $current->copy()->endOfWeek();

            $weekData = DB::table('task_submissions')
                ->join('users', 'task_submissions.user_id', '=', 'users.id')
                ->where('users.company_id', $company->id)
                ->whereBetween('task_submissions.completed_at', [$weekStart, $weekEnd])
                ->selectRaw('
                    COUNT(CASE WHEN status = "completed" THEN 1 END) as completions,
                    COUNT(DISTINCT task_submissions.user_id) as active_students,
                    AVG(CASE WHEN status = "completed" THEN actual_hours END) as avg_hours
                ')
                ->first();

            $trends[] = [
                'week' => $weekStart->format('Y-m-d'),
                'completions' => $weekData->completions ?? 0,
                'active_students' => $weekData->active_students ?? 0,
                'avg_hours' => round(($weekData->avg_hours ?? 0) / 60, 1),
            ];

            $current->addWeek();
        }

        return $trends;
    }

    /**
     * 出席パターン分析
     */
    private function getAttendancePatterns(Company $company, Carbon $startDate, Carbon $endDate)
    {
        // 曜日別出席率
        $dayOfWeekPattern = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('
                DAYOFWEEK(attendance_date) as day_of_week,
                COUNT(CASE WHEN status = "present" THEN 1 END) as present,
                COUNT(*) as total
            ')
            ->groupBy('day_of_week')
            ->get()
            ->mapWithKeys(function ($item) {
                $days = ['日', '月', '火', '水', '木', '金', '土'];
                return [
                    $days[$item->day_of_week - 1] => [
                        'rate' => $item->total > 0 ? round(($item->present / $item->total) * 100, 1) : 0,
                        'count' => $item->present,
                    ]
                ];
            });

        // 時間帯別チェックイン
        $timePattern = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->whereNotNull('check_in_time')
            ->selectRaw('
                HOUR(check_in_time) as hour,
                COUNT(*) as count
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // 連続出席分析
        $consecutiveAttendance = DB::table('users')
            ->where('company_id', $company->id)
            ->where('role', 'student')
            ->selectRaw('
                AVG(consecutive_days) as avg_consecutive,
                MAX(consecutive_days) as max_consecutive,
                COUNT(CASE WHEN consecutive_days >= 5 THEN 1 END) as over_5_days
            ')
            ->first();

        return [
            'by_day_of_week' => $dayOfWeekPattern,
            'by_time' => $timePattern->toArray(),
            'consecutive_stats' => [
                'average' => round($consecutiveAttendance->avg_consecutive ?? 0, 1),
                'maximum' => $consecutiveAttendance->max_consecutive ?? 0,
                'over_5_days_count' => $consecutiveAttendance->over_5_days ?? 0,
            ],
        ];
    }

    /**
     * 言語別分布
     */
    private function getLanguageDistribution(Company $company)
    {
        return DB::table('users')
            ->join('languages', 'users.language_id', '=', 'languages.id')
            ->where('users.company_id', $company->id)
            ->where('users.role', 'student')
            ->selectRaw('
                languages.name,
                languages.code,
                COUNT(users.id) as student_count,
                AVG(users.total_study_hours) as avg_study_hours
            ')
            ->groupBy('languages.id', 'languages.name', 'languages.code')
            ->orderByDesc('student_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'student_count' => $item->student_count,
                    'avg_study_hours' => round($item->avg_study_hours / 60, 1),
                ];
            });
    }

    /**
     * 課題パフォーマンス分析
     */
    private function getTaskPerformance(Company $company)
    {
        $taskStats = DB::table('tasks')
            ->leftJoin('task_submissions', function ($join) use ($company) {
                $join->on('tasks.id', '=', 'task_submissions.task_id')
                    ->join('users', 'task_submissions.user_id', '=', 'users.id')
                    ->where('users.company_id', '=', $company->id);
            })
            ->where(function ($q) use ($company) {
                $q->whereNull('tasks.company_id')
                    ->orWhere('tasks.company_id', $company->id);
            })
            ->selectRaw('
                tasks.id,
                tasks.title,
                tasks.category_major,
                tasks.difficulty,
                tasks.estimated_hours,
                COUNT(DISTINCT task_submissions.user_id) as attempted_count,
                COUNT(DISTINCT CASE WHEN task_submissions.status = "completed" THEN task_submissions.user_id END) as completed_count,
                AVG(CASE WHEN task_submissions.status = "completed" THEN task_submissions.score END) as avg_score,
                AVG(CASE WHEN task_submissions.status = "completed" THEN task_submissions.actual_hours END) as avg_actual_hours
            ')
            ->groupBy('tasks.id', 'tasks.title', 'tasks.category_major', 'tasks.difficulty', 'tasks.estimated_hours')
            ->orderBy('tasks.display_order')
            ->get();

        // 難易度別統計
        $difficultyStats = $taskStats->groupBy('difficulty')->map(function ($tasks, $difficulty) {
            return [
                'count' => $tasks->count(),
                'avg_completion_rate' => $tasks->avg(function ($task) {
                    return $task->attempted_count > 0 
                        ? ($task->completed_count / $task->attempted_count) * 100 
                        : 0;
                }),
                'avg_score' => $tasks->avg('avg_score'),
            ];
        });

        // カテゴリ別統計
        $categoryStats = $taskStats->groupBy('category_major')->map(function ($tasks, $category) {
            return [
                'count' => $tasks->count(),
                'total_attempted' => $tasks->sum('attempted_count'),
                'total_completed' => $tasks->sum('completed_count'),
                'avg_score' => $tasks->avg('avg_score'),
            ];
        });

        return [
            'by_difficulty' => $difficultyStats,
            'by_category' => $categoryStats,
            'hardest_tasks' => $taskStats->sortBy(function ($task) {
                return $task->attempted_count > 0 
                    ? $task->completed_count / $task->attempted_count 
                    : 1;
            })->take(5)->values(),
            'most_time_consuming' => $taskStats->sortByDesc('avg_actual_hours')->take(5)->values(),
        ];
    }

    /**
     * 受講生ランキング
     */
    private function getStudentRankings(Company $company)
    {
        // 総合ランキング
        $overallRanking = DB::table('users')
            ->leftJoin('task_submissions', 'users.id', '=', 'task_submissions.user_id')
            ->where('users.company_id', $company->id)
            ->where('users.role', 'student')
            ->where('users.status', 'active')
            ->selectRaw('
                users.id,
                users.name,
                COUNT(CASE WHEN task_submissions.status = "completed" THEN 1 END) as completed_count,
                AVG(CASE WHEN task_submissions.status = "completed" THEN task_submissions.score END) as avg_score,
                SUM(CASE WHEN task_submissions.status = "completed" THEN 1 ELSE 0 END) * 100.0 / 
                    (SELECT COUNT(*) FROM tasks WHERE company_id IS NULL OR company_id = ?) as completion_rate,
                users.total_study_hours,
                users.consecutive_days
            ', [$company->id])
            ->groupBy('users.id', 'users.name', 'users.total_study_hours', 'users.consecutive_days')
            ->orderByDesc('completion_rate')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get();

        // 今週のトップパフォーマー
        $weeklyTop = DB::table('users')
            ->join('task_submissions', 'users.id', '=', 'task_submissions.user_id')
            ->where('users.company_id', $company->id)
            ->where('users.role', 'student')
            ->where('task_submissions.completed_at', '>=', Carbon::now()->startOfWeek())
            ->where('task_submissions.status', 'completed')
            ->selectRaw('
                users.id,
                users.name,
                COUNT(*) as weekly_completions,
                AVG(task_submissions.score) as weekly_avg_score
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('weekly_completions')
            ->limit(5)
            ->get();

        return [
            'overall' => $overallRanking,
            'weekly' => $weeklyTop,
        ];
    }

    /**
     * 完了予測
     */
    private function getCompletionForecast(Company $company)
    {
        // 過去4週間の完了率から予測
        $historicalData = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

            $weekData = DB::table('task_submissions')
                ->join('users', 'task_submissions.user_id', '=', 'users.id')
                ->where('users.company_id', $company->id)
                ->whereBetween('task_submissions.completed_at', [$weekStart, $weekEnd])
                ->where('task_submissions.status', 'completed')
                ->count();

            $historicalData[] = $weekData;
        }

        // 単純な線形回帰で予測
        $avgWeeklyCompletion = array_sum($historicalData) / count($historicalData);
        $totalTasks = Task::where(function ($q) use ($company) {
            $q->whereNull('company_id')->orWhere('company_id', $company->id);
        })->count();
        
        $totalStudents = $company->students()->where('status', 'active')->count();
        $totalExpected = $totalTasks * $totalStudents;
        
        $currentCompleted = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->where('task_submissions.status', 'completed')
            ->count();

        $remainingTasks = $totalExpected - $currentCompleted;
        $weeksToComplete = $avgWeeklyCompletion > 0 
            ? ceil($remainingTasks / $avgWeeklyCompletion)
            : 999;

        return [
            'current_progress' => $totalExpected > 0 ? round(($currentCompleted / $totalExpected) * 100, 1) : 0,
            'weekly_average' => round($avgWeeklyCompletion, 0),
            'estimated_weeks' => $weeksToComplete,
            'estimated_date' => Carbon::now()->addWeeks($weeksToComplete)->format('Y-m-d'),
            'confidence' => $this->calculateConfidence($historicalData),
        ];
    }

    /**
     * リスク分析
     */
    private function getRiskAnalysis(Company $company)
    {
        $risks = [];

        // 2週間未出席の受講生
        $absentStudents = User::where('company_id', $company->id)
            ->where('role', 'student')
            ->where('status', 'active')
            ->whereDoesntHave('attendances', function ($q) {
                $q->where('attendance_date', '>=', Carbon::now()->subWeeks(2))
                    ->where('status', 'present');
            })
            ->select('id', 'name', 'last_login_at')
            ->get();

        if ($absentStudents->count() > 0) {
            $risks[] = [
                'type' => 'attendance',
                'level' => 'high',
                'message' => "{$absentStudents->count()}名の受講生が2週間以上出席していません",
                'affected_users' => $absentStudents,
            ];
        }

        // 進捗遅延の受講生
        $slowProgressStudents = DB::table('users')
            ->leftJoin('task_submissions', function ($join) {
                $join->on('users.id', '=', 'task_submissions.user_id')
                    ->where('task_submissions.status', '=', 'completed');
            })
            ->where('users.company_id', $company->id)
            ->where('users.role', 'student')
            ->where('users.status', 'active')
            ->where('users.created_at', '<=', Carbon::now()->subMonths(1))
            ->groupBy('users.id', 'users.name')
            ->havingRaw('COUNT(task_submissions.id) < 5')
            ->select('users.id', 'users.name', DB::raw('COUNT(task_submissions.id) as completed_count'))
            ->get();

        if ($slowProgressStudents->count() > 0) {
            $risks[] = [
                'type' => 'progress',
                'level' => 'medium',
                'message' => "{$slowProgressStudents->count()}名の受講生の進捗が遅れています",
                'affected_users' => $slowProgressStudents,
            ];
        }

        // 低スコアの傾向
        $lowScoreTasks = DB::table('tasks')
            ->join('task_submissions', 'tasks.id', '=', 'task_submissions.task_id')
            ->join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->where('task_submissions.status', 'completed')
            ->groupBy('tasks.id', 'tasks.title')
            ->having('avg_score', '<', 60)
            ->selectRaw('
                tasks.id,
                tasks.title,
                AVG(task_submissions.score) as avg_score,
                COUNT(*) as submission_count
            ')
            ->get();

        if ($lowScoreTasks->count() > 0) {
            $risks[] = [
                'type' => 'performance',
                'level' => 'medium',
                'message' => "{$lowScoreTasks->count()}個の課題で平均スコアが60点未満です",
                'affected_tasks' => $lowScoreTasks,
            ];
        }

        return $risks;
    }

    /**
     * 学習曲線
     */
    private function getLearningCurve(User $student, Carbon $startDate, Carbon $endDate)
    {
        return DB::table('task_submissions')
            ->where('user_id', $student->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->orderBy('completed_at')
            ->selectRaw('
                DATE(completed_at) as date,
                COUNT(*) as tasks_completed,
                AVG(score) as avg_score,
                SUM(actual_hours) as total_hours
            ')
            ->groupBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'tasks_completed' => $item->tasks_completed,
                    'avg_score' => round($item->avg_score ?? 0, 1),
                    'study_hours' => round(($item->total_hours ?? 0) / 60, 1),
                ];
            });
    }

    /**
     * スキルレーダーチャート用データ
     */
    private function getSkillRadar(User $student)
    {
        $categories = DB::table('tasks')
            ->join('task_submissions', 'tasks.id', '=', 'task_submissions.task_id')
            ->where('task_submissions.user_id', $student->id)
            ->where('task_submissions.status', 'completed')
            ->groupBy('tasks.category_major')
            ->selectRaw('
                tasks.category_major as category,
                AVG(task_submissions.score) as avg_score,
                COUNT(*) as completed_count
            ')
            ->get();

        return $categories->map(function ($cat) {
            return [
                'category' => $cat->category,
                'score' => round($cat->avg_score ?? 0, 1),
                'completed' => $cat->completed_count,
            ];
        });
    }

    /**
     * 時間配分分析
     */
    private function getTimeDistribution(User $student, Carbon $startDate, Carbon $endDate)
    {
        // 曜日別
        $byDayOfWeek = DB::table('attendances')
            ->where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('
                DAYOFWEEK(attendance_date) as day,
                SUM(study_minutes) as total_minutes
            ')
            ->groupBy('day')
            ->get();

        // 時間帯別
        $byHour = DB::table('attendances')
            ->where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->whereNotNull('check_in_time')
            ->selectRaw('
                HOUR(check_in_time) as hour,
                COUNT(*) as count
            ')
            ->groupBy('hour')
            ->get();

        // カテゴリ別
        $byCategory = DB::table('tasks')
            ->join('task_submissions', 'tasks.id', '=', 'task_submissions.task_id')
            ->where('task_submissions.user_id', $student->id)
            ->where('task_submissions.status', 'completed')
            ->whereBetween('task_submissions.completed_at', [$startDate, $endDate])
            ->groupBy('tasks.category_major')
            ->selectRaw('
                tasks.category_major as category,
                SUM(task_submissions.actual_hours) as total_minutes
            ')
            ->get();

        return [
            'by_day_of_week' => $byDayOfWeek,
            'by_hour' => $byHour,
            'by_category' => $byCategory,
        ];
    }

    /**
     * パフォーマンス比較
     */
    private function getPerformanceComparison(User $student)
    {
        $company = $student->company;
        
        // 同じ言語を学習している他の受講生との比較
        $peerStats = DB::table('users')
            ->leftJoin('task_submissions', function ($join) {
                $join->on('users.id', '=', 'task_submissions.user_id')
                    ->where('task_submissions.status', '=', 'completed');
            })
            ->where('users.company_id', $company->id)
            ->where('users.language_id', $student->language_id)
            ->where('users.role', 'student')
            ->where('users.status', 'active')
            ->groupBy('users.id')
            ->selectRaw('
                COUNT(task_submissions.id) as completed_count,
                AVG(task_submissions.score) as avg_score,
                SUM(task_submissions.actual_hours) as total_hours
            ')
            ->get();

        $studentStats = $peerStats->where('id', $student->id)->first();

        return [
            'student' => [
                'completed_count' => $studentStats->completed_count ?? 0,
                'avg_score' => round($studentStats->avg_score ?? 0, 1),
                'total_hours' => round(($studentStats->total_hours ?? 0) / 60, 1),
            ],
            'peer_average' => [
                'completed_count' => round($peerStats->avg('completed_count'), 1),
                'avg_score' => round($peerStats->avg('avg_score'), 1),
                'total_hours' => round($peerStats->avg('total_hours') / 60, 1),
            ],
            'percentile' => $this->calculatePercentile($studentStats, $peerStats),
        ];
    }

    /**
     * 強みと弱み分析
     */
    private function getStrengthsWeaknesses(User $student)
    {
        $taskPerformance = DB::table('tasks')
            ->join('task_submissions', 'tasks.id', '=', 'task_submissions.task_id')
            ->where('task_submissions.user_id', $student->id)
            ->where('task_submissions.status', 'completed')
            ->select('tasks.*', 'task_submissions.score', 'task_submissions.actual_hours')
            ->get();

        // 高スコアの課題（強み）
        $strengths = $taskPerformance->where('score', '>=', 80)
            ->groupBy('category_major')
            ->map(function ($tasks, $category) {
                return [
                    'category' => $category,
                    'count' => $tasks->count(),
                    'avg_score' => round($tasks->avg('score'), 1),
                ];
            })
            ->sortByDesc('avg_score')
            ->take(3);

        // 低スコアまたは時間がかかった課題（弱み）
        $weaknesses = $taskPerformance->filter(function ($task) {
            return $task->score < 70 || 
                   ($task->actual_hours / 60) > ($task->estimated_hours * 1.5);
        })
            ->groupBy('category_major')
            ->map(function ($tasks, $category) {
                return [
                    'category' => $category,
                    'count' => $tasks->count(),
                    'avg_score' => round($tasks->avg('score'), 1),
                    'time_ratio' => round($tasks->avg(function ($t) {
                        return ($t->actual_hours / 60) / $t->estimated_hours;
                    }), 2),
                ];
            })
            ->sortBy('avg_score')
            ->take(3);

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
        ];
    }

    /**
     * 完了予測（個人）
     */
    private function getPredictedCompletion(User $student)
    {
        // 過去の完了ペースから予測
        $completedTasks = TaskSubmission::where('user_id', $student->id)
            ->where('status', 'completed')
            ->count();

        $totalTasks = Task::where('language_id', $student->language_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $student->company_id);
            })
            ->count();

        $remainingTasks = $totalTasks - $completedTasks;

        // 週あたりの平均完了数
        $weeksSinceStart = Carbon::parse($student->enrollment_date)->diffInWeeks(now()) ?: 1;
        $avgTasksPerWeek = $completedTasks / $weeksSinceStart;

        $weeksToComplete = $avgTasksPerWeek > 0 
            ? ceil($remainingTasks / $avgTasksPerWeek)
            : 999;

        return [
            'completed' => $completedTasks,
            'total' => $totalTasks,
            'remaining' => $remainingTasks,
            'progress_percentage' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            'avg_tasks_per_week' => round($avgTasksPerWeek, 1),
            'estimated_weeks' => $weeksToComplete,
            'estimated_date' => Carbon::now()->addWeeks($weeksToComplete)->format('Y-m-d'),
        ];
    }

    /**
     * 信頼度計算
     */
    private function calculateConfidence(array $data): string
    {
        if (count($data) < 4) return 'low';
        
        $avg = array_sum($data) / count($data);
        $variance = array_sum(array_map(function ($x) use ($avg) {
            return pow($x - $avg, 2);
        }, $data)) / count($data);
        
        $stdDev = sqrt($variance);
        $cv = $avg > 0 ? ($stdDev / $avg) : 0;
        
        if ($cv < 0.2) return 'high';
        if ($cv < 0.5) return 'medium';
        return 'low';
    }

    /**
     * パーセンタイル計算
     */
    private function calculatePercentile($studentStats, $peerStats): int
    {
        if (!$studentStats || $peerStats->isEmpty()) return 0;
        
        $studentScore = $studentStats->completed_count * 0.5 + ($studentStats->avg_score ?? 0) * 0.5;
        
        $belowCount = $peerStats->filter(function ($peer) use ($studentScore) {
            $peerScore = $peer->completed_count * 0.5 + ($peer->avg_score ?? 0) * 0.5;
            return $peerScore < $studentScore;
        })->count();
        
        return round(($belowCount / $peerStats->count()) * 100);
    }
}