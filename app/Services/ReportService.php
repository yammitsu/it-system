<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Attendance;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ReportService
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * 週次レポート生成（簡易版）
     */
    public function generateWeeklyReport(Company $company, Carbon $weekStart = null)
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $data = [
            'company' => $company,
            'period' => [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
                'week_number' => $weekStart->weekOfYear,
            ],
            'summary' => $this->getWeeklySummary($company, $weekStart, $weekEnd),
            'attendance' => $this->getWeeklyAttendance($company, $weekStart, $weekEnd),
            'progress' => $this->getWeeklyProgress($company, $weekStart, $weekEnd),
            'highlights' => $this->getWeeklyHighlights($company, $weekStart, $weekEnd),
        ];

        return $this->exportReport($data, 'weekly', $company->id);
    }

    /**
     * 月次レポート生成（詳細版）
     */
    public function generateMonthlyReport(Company $company, Carbon $month = null)
    {
        $month = $month ?? Carbon::now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        // 分析データ取得
        $analytics = $this->analyticsService->getCompanyAnalytics(
            $company,
            $month,
            $monthEnd
        );

        $data = [
            'company' => $company,
            'period' => [
                'month' => $month->format('Y年m月'),
                'start' => $month->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ],
            'analytics' => $analytics,
            'detailed_progress' => $this->getDetailedProgress($company, $month, $monthEnd),
            'student_reports' => $this->getStudentReports($company, $month, $monthEnd),
            'task_analysis' => $this->getTaskAnalysis($company, $month, $monthEnd),
            'recommendations' => $this->generateRecommendations($analytics),
        ];

        return $this->exportReport($data, 'monthly', $company->id);
    }

    /**
     * 個人レポート生成
     */
    public function generateStudentReport(User $student, Carbon $startDate = null, Carbon $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->subMonth();
        $endDate = $endDate ?? Carbon::now();

        $analytics = $this->analyticsService->getStudentAnalytics(
            $student,
            $startDate,
            $endDate
        );

        $data = [
            'student' => $student,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'analytics' => $analytics,
            'task_details' => $this->getStudentTaskDetails($student, $startDate, $endDate),
            'attendance_record' => $this->getStudentAttendanceRecord($student, $startDate, $endDate),
            'achievements' => $this->getStudentAchievements($student),
            'feedback' => $this->getStudentFeedback($student, $startDate, $endDate),
        ];

        return $this->exportReport($data, 'student', $student->id);
    }

    /**
     * カスタムレポート生成
     */
    public function generateCustomReport(array $params)
    {
        $type = $params['type'] ?? 'general';
        $filters = $params['filters'] ?? [];
        $format = $params['format'] ?? 'pdf';

        $data = [];

        // レポートタイプに応じてデータ収集
        switch ($type) {
            case 'attendance':
                $data = $this->getAttendanceReport($filters);
                break;
            case 'progress':
                $data = $this->getProgressReport($filters);
                break;
            case 'performance':
                $data = $this->getPerformanceReport($filters);
                break;
            case 'comparison':
                $data = $this->getComparisonReport($filters);
                break;
            default:
                $data = $this->getGeneralReport($filters);
        }

        return $this->exportReport($data, 'custom', null, $format);
    }

    /**
     * 週次サマリー取得
     */
    private function getWeeklySummary(Company $company, Carbon $weekStart, Carbon $weekEnd)
    {
        $activeStudents = Attendance::join('users', 'attendances.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('attendance_date', [$weekStart, $weekEnd])
            ->where('status', 'present')
            ->distinct('user_id')
            ->count('user_id');

        $tasksCompleted = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->where('status', 'completed')
            ->count();

        $avgScore = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->where('status', 'completed')
            ->avg('score');

        $totalStudyHours = Attendance::join('users', 'attendances.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('attendance_date', [$weekStart, $weekEnd])
            ->sum('study_minutes') / 60;

        return [
            'active_students' => $activeStudents,
            'tasks_completed' => $tasksCompleted,
            'avg_score' => round($avgScore ?? 0, 1),
            'total_study_hours' => round($totalStudyHours, 1),
        ];
    }

    /**
     * 週次出席情報
     */
    private function getWeeklyAttendance(Company $company, Carbon $weekStart, Carbon $weekEnd)
    {
        $attendanceByDay = [];
        $current = $weekStart->copy();

        while ($current <= $weekEnd) {
            if (!$current->isWeekend()) {
                $dayAttendance = Attendance::join('users', 'attendances.user_id', '=', 'users.id')
                    ->where('users.company_id', $company->id)
                    ->where('attendance_date', $current->format('Y-m-d'))
                    ->selectRaw('
                        COUNT(CASE WHEN status = "present" THEN 1 END) as present,
                        COUNT(CASE WHEN status = "absent" THEN 1 END) as absent,
                        COUNT(CASE WHEN status = "late" THEN 1 END) as late
                    ')
                    ->first();

                $attendanceByDay[$current->format('m/d')] = [
                    'present' => $dayAttendance->present ?? 0,
                    'absent' => $dayAttendance->absent ?? 0,
                    'late' => $dayAttendance->late ?? 0,
                ];
            }
            $current->addDay();
        }

        return $attendanceByDay;
    }

    /**
     * 週次進捗情報
     */
    private function getWeeklyProgress(Company $company, Carbon $weekStart, Carbon $weekEnd)
    {
        $progressByCategory = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->join('tasks', 'task_submissions.task_id', '=', 'tasks.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('task_submissions.completed_at', [$weekStart, $weekEnd])
            ->where('task_submissions.status', 'completed')
            ->groupBy('tasks.category_major')
            ->selectRaw('
                tasks.category_major as category,
                COUNT(*) as completed_count,
                AVG(task_submissions.score) as avg_score
            ')
            ->get();

        return $progressByCategory;
    }

    /**
     * 週次ハイライト
     */
    private function getWeeklyHighlights(Company $company, Carbon $weekStart, Carbon $weekEnd)
    {
        // トップパフォーマー
        $topPerformers = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->where('status', 'completed')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('
                users.name,
                COUNT(*) as task_count,
                AVG(score) as avg_score
            ')
            ->orderByDesc('task_count')
            ->limit(3)
            ->get();

        // 難関課題クリア
        $difficultTasksCleared = TaskSubmission::join('users', 'task_submissions.user_id', '=', 'users.id')
            ->join('tasks', 'task_submissions.task_id', '=', 'tasks.id')
            ->where('users.company_id', $company->id)
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->where('task_submissions.status', 'completed')
            ->where('tasks.difficulty', 'expert')
            ->select('users.name', 'tasks.title', 'task_submissions.score')
            ->orderByDesc('task_submissions.score')
            ->limit(5)
            ->get();

        return [
            'top_performers' => $topPerformers,
            'difficult_tasks_cleared' => $difficultTasksCleared,
        ];
    }

    /**
     * 詳細進捗情報
     */
    private function getDetailedProgress(Company $company, Carbon $monthStart, Carbon $monthEnd)
    {
        $students = $company->students()->where('status', 'active')->get();

        return $students->map(function ($student) use ($monthStart, $monthEnd) {
            $submissions = TaskSubmission::where('user_id', $student->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->get();

            return [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'language' => $student->language->name ?? '-',
                ],
                'statistics' => [
                    'started' => $submissions->count(),
                    'completed' => $submissions->where('status', 'completed')->count(),
                    'avg_score' => round($submissions->where('status', 'completed')->avg('score') ?? 0, 1),
                    'total_hours' => round($submissions->sum('actual_hours') / 60, 1),
                ],
                'attendance_rate' => $this->calculateAttendanceRate($student, $monthStart, $monthEnd),
            ];
        });
    }

    /**
     * 学生レポート取得
     */
    private function getStudentReports(Company $company, Carbon $monthStart, Carbon $monthEnd)
    {
        $students = $company->students()
            ->where('status', 'active')
            ->get();

        $reports = [];

        foreach ($students as $student) {
            $analytics = $this->analyticsService->getStudentAnalytics(
                $student,
                $monthStart,
                $monthEnd
            );

            $reports[] = [
                'student' => $student,
                'summary' => [
                    'completion_rate' => $analytics['predicted_completion']['progress_percentage'] ?? 0,
                    'avg_score' => $this->getAverageScore($student, $monthStart, $monthEnd),
                    'study_hours' => $this->getTotalStudyHours($student, $monthStart, $monthEnd),
                    'attendance_rate' => $this->calculateAttendanceRate($student, $monthStart, $monthEnd),
                ],
                'strengths' => $analytics['strengths_weaknesses']['strengths'] ?? [],
                'areas_for_improvement' => $analytics['strengths_weaknesses']['weaknesses'] ?? [],
            ];
        }

        return $reports;
    }

    /**
     * 課題分析
     */
    private function getTaskAnalysis(Company $company, Carbon $monthStart, Carbon $monthEnd)
    {
        $taskStats = Task::with(['submissions' => function ($q) use ($company, $monthStart, $monthEnd) {
                $q->join('users', 'task_submissions.user_id', '=', 'users.id')
                    ->where('users.company_id', $company->id)
                    ->whereBetween('task_submissions.created_at', [$monthStart, $monthEnd]);
            }])
            ->where(function ($q) use ($company) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->get()
            ->map(function ($task) {
                $submissions = $task->submissions;
                $completed = $submissions->where('status', 'completed');

                return [
                    'task' => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'category' => $task->category_major,
                        'difficulty' => $task->difficulty,
                    ],
                    'statistics' => [
                        'attempted' => $submissions->count(),
                        'completed' => $completed->count(),
                        'completion_rate' => $submissions->count() > 0 
                            ? round(($completed->count() / $submissions->count()) * 100, 1)
                            : 0,
                        'avg_score' => round($completed->avg('score') ?? 0, 1),
                        'avg_time' => round($completed->avg('actual_hours') / 60 ?? 0, 1),
                    ],
                ];
            })
            ->filter(function ($item) {
                return $item['statistics']['attempted'] > 0;
            });

        return [
            'most_attempted' => $taskStats->sortByDesc('statistics.attempted')->take(5)->values(),
            'highest_completion' => $taskStats->sortByDesc('statistics.completion_rate')->take(5)->values(),
            'lowest_completion' => $taskStats->sortBy('statistics.completion_rate')->take(5)->values(),
            'highest_scores' => $taskStats->sortByDesc('statistics.avg_score')->take(5)->values(),
        ];
    }

    /**
     * 推奨事項生成
     */
    private function generateRecommendations(array $analytics)
    {
        $recommendations = [];

        // リスク分析に基づく推奨
        foreach ($analytics['risk_analysis'] as $risk) {
            switch ($risk['type']) {
                case 'attendance':
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => '出席管理',
                        'recommendation' => '長期欠席者へのフォローアップが必要です。個別面談やメンタリングの実施を検討してください。',
                        'affected_count' => count($risk['affected_users']),
                    ];
                    break;

                case 'progress':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'category' => '進捗管理',
                        'recommendation' => '進捗が遅れている受講生に対して、追加サポートや学習計画の見直しを行ってください。',
                        'affected_count' => count($risk['affected_users']),
                    ];
                    break;

                case 'performance':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'category' => '課題改善',
                        'recommendation' => '低スコアの課題について、内容の見直しや追加説明資料の提供を検討してください。',
                        'affected_count' => count($risk['affected_tasks']),
                    ];
                    break;
            }
        }

        // 完了予測に基づく推奨
        if ($analytics['completion_forecast']['estimated_weeks'] > 12) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => '進捗改善',
                'recommendation' => '現在のペースでは完了まで時間がかかります。学習時間の確保や効率的な学習方法の導入を検討してください。',
            ];
        }

        return $recommendations;
    }

    /**
     * レポートエクスポート
     */
    private function exportReport(array $data, string $type, ?int $entityId, string $format = 'pdf')
    {
        $timestamp = Carbon::now()->format('YmdHis');
        $filename = "{$type}_report_{$entityId}_{$timestamp}";

        switch ($format) {
            case 'pdf':
                return $this->exportPdf($data, $type, $filename);
            case 'excel':
                return $this->exportExcel($data, $type, $filename);
            case 'json':
                return $this->exportJson($data, $filename);
            default:
                return $data;
        }
    }

    /**
     * PDF出力
     */
    private function exportPdf(array $data, string $type, string $filename)
    {
        $view = "reports.{$type}";
        
        if (!View::exists($view)) {
            $view = 'reports.default';
        }

        $pdf = PDF::loadView($view, $data);
        $pdf->setPaper('A4', 'portrait');

        $path = "reports/{$filename}.pdf";
        Storage::put($path, $pdf->output());

        return [
            'path' => $path,
            'url' => Storage::url($path),
            'filename' => "{$filename}.pdf",
        ];
    }

    /**
     * Excel出力
     */
    private function exportExcel(array $data, string $type, string $filename)
    {
        // Excelエクスポートクラスを使用（実装は省略）
        $path = "reports/{$filename}.xlsx";

        return [
            'path' => $path,
            'url' => Storage::url($path),
            'filename' => "{$filename}.xlsx",
        ];
    }

    /**
     * JSON出力
     */
    private function exportJson(array $data, string $filename)
    {
        $path = "reports/{$filename}.json";
        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'path' => $path,
            'url' => Storage::url($path),
            'filename' => "{$filename}.json",
        ];
    }

    /**
     * ヘルパーメソッド
     */
    private function calculateAttendanceRate(User $student, Carbon $startDate, Carbon $endDate): float
    {
        $businessDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (!$current->isWeekend() && !$current->isFuture()) {
                $businessDays++;
            }
            $current->addDay();
        }

        if ($businessDays === 0) return 0;

        $presentDays = Attendance::where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'present')
            ->count();

        return round(($presentDays / $businessDays) * 100, 1);
    }

    private function getAverageScore(User $student, Carbon $startDate, Carbon $endDate): float
    {
        return round(
            TaskSubmission::where('user_id', $student->id)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->avg('score') ?? 0,
            1
        );
    }

    private function getTotalStudyHours(User $student, Carbon $startDate, Carbon $endDate): float
    {
        $minutes = Attendance::where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->sum('study_minutes');

        return round($minutes / 60, 1);
    }

    private function getStudentTaskDetails(User $student, Carbon $startDate, Carbon $endDate)
    {
        return TaskSubmission::with('task')
            ->where('user_id', $student->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function getStudentAttendanceRecord(User $student, Carbon $startDate, Carbon $endDate)
    {
        return Attendance::where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date', 'desc')
            ->get();
    }

    private function getStudentAchievements(User $student)
    {
        return $student->badges()
            ->orderBy('earned_at', 'desc')
            ->get();
    }

    private function getStudentFeedback(User $student, Carbon $startDate, Carbon $endDate)
    {
        return TaskSubmission::where('user_id', $student->id)
            ->whereBetween('evaluated_at', [$startDate, $endDate])
            ->whereNotNull('feedback')
            ->with('task', 'evaluator')
            ->orderBy('evaluated_at', 'desc')
            ->get();
    }
}