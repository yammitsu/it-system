<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * ダッシュボード表示
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // 権限による表示内容の切り替え
        if ($user->role === 'system_admin') {
            return $this->systemAdminDashboard();
        } elseif ($user->role === 'company_admin') {
            return $this->companyAdminDashboard($user);
        } else {
            return $this->teacherDashboard($user);
        }
    }

    /**
     * 講師用ダッシュボード
     */
    private function teacherDashboard($user)
    {
        // 本日のシフト
        $todayShift = Shift::where('teacher_id', $user->id)
            ->where('shift_date', Carbon::today())
            ->with(['company', 'language'])
            ->first();

        // 今週のシフト
        $weekShifts = Shift::where('teacher_id', $user->id)
            ->whereBetween('shift_date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ])
            ->orderBy('shift_date')
            ->get();

        // 担当受講生の統計
        $studentStats = $this->getStudentStats($user);

        // 最近の提出物
        $recentSubmissions = TaskSubmission::whereHas('task', function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
            ->where('status', 'completed')
            ->whereNull('evaluated_at')
            ->with(['user', 'task'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        // 課題統計
        $taskStats = $this->getTaskStats($user);

        // 今日の出席者
        $todayAttendees = [];
        if ($todayShift) {
            $todayAttendees = Attendance::where('attendance_date', Carbon::today())
                ->where('status', 'present')
                ->whereHas('user', function ($q) use ($todayShift) {
                    $q->where('company_id', $todayShift->company_id);
                })
                ->with('user')
                ->get();
        }

        return view('teacher.dashboard', compact(
            'todayShift',
            'weekShifts',
            'studentStats',
            'recentSubmissions',
            'taskStats',
            'todayAttendees'
        ));
    }

    /**
     * 企業管理者用ダッシュボード
     */
    private function companyAdminDashboard($user)
    {
        $company = $user->company;

        // 企業統計
        $companyStats = [
            'total_users' => $company->users()->count(),
            'active_students' => $company->students()->where('status', 'active')->count(),
            'total_teachers' => $company->teachers()->count(),
            'completion_rate' => $this->calculateCompanyCompletionRate($company),
        ];

        // 言語別受講生数
        $languageStats = User::where('company_id', $company->id)
            ->where('role', 'student')
            ->select('language_id', DB::raw('count(*) as count'))
            ->groupBy('language_id')
            ->with('language')
            ->get();

        // 最近の活動
        $recentActivities = $this->getRecentActivities($company);

        // 今月の出席率
        $attendanceRate = $this->calculateMonthlyAttendanceRate($company);

        // 課題進捗
        $taskProgress = $this->getCompanyTaskProgress($company);

        // 講師用データも含める
        $teacherData = $this->teacherDashboard($user);

        return view('teacher.dashboard', array_merge($teacherData, [
            'companyStats' => $companyStats,
            'languageStats' => $languageStats,
            'recentActivities' => $recentActivities,
            'attendanceRate' => $attendanceRate,
            'taskProgress' => $taskProgress,
            'isCompanyAdmin' => true,
        ]));
    }

    /**
     * システム管理者用ダッシュボード（別途実装）
     */
    private function systemAdminDashboard()
    {
        // システム管理者用は別コントローラーで実装
        return redirect()->route('admin.dashboard');
    }

    /**
     * 受講生統計取得
     */
    private function getStudentStats($user)
    {
        $baseQuery = User::where('role', 'student');

        if ($user->role === 'teacher') {
            // 講師の場合は担当企業の受講生
            $companyIds = Shift::where('teacher_id', $user->id)
                ->distinct()
                ->pluck('company_id');
            $baseQuery->whereIn('company_id', $companyIds);
        } elseif ($user->role === 'company_admin') {
            // 企業管理者の場合は自社の受講生
            $baseQuery->where('company_id', $user->company_id);
        }

        $totalStudents = $baseQuery->count();
        $activeStudents = clone $baseQuery;
        $activeStudents = $activeStudents->where('status', 'active')->count();

        // 平均進捗率
        $avgProgress = TaskSubmission::whereIn('user_id', $baseQuery->pluck('id'))
            ->selectRaw('user_id, COUNT(CASE WHEN status = "completed" THEN 1 END) * 100.0 / COUNT(*) as progress')
            ->groupBy('user_id')
            ->get()
            ->avg('progress');

        return [
            'total' => $totalStudents,
            'active' => $activeStudents,
            'average_progress' => round($avgProgress ?? 0, 1),
        ];
    }

    /**
     * 課題統計取得
     */
    private function getTaskStats($user)
    {
        $baseQuery = Task::query();

        if ($user->role === 'teacher') {
            $baseQuery->where('created_by', $user->id);
        } elseif ($user->role === 'company_admin') {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        $totalTasks = $baseQuery->count();
        $activeTasks = clone $baseQuery;
        $activeTasks = $activeTasks->where('is_active', true)->count();

        // 提出待ち
        $pendingEvaluations = TaskSubmission::whereIn('task_id', $baseQuery->pluck('id'))
            ->where('status', 'completed')
            ->whereNull('evaluated_at')
            ->count();

        return [
            'total' => $totalTasks,
            'active' => $activeTasks,
            'pending_evaluations' => $pendingEvaluations,
        ];
    }

    /**
     * 企業の完了率計算
     */
    private function calculateCompanyCompletionRate($company)
    {
        $students = $company->students()->pluck('id');
        
        if ($students->isEmpty()) {
            return 0;
        }

        $totalTasks = Task::where(function ($q) use ($company) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->count();

        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = TaskSubmission::whereIn('user_id', $students)
            ->where('status', 'completed')
            ->count();

        $expectedTotal = $students->count() * $totalTasks;
        
        return $expectedTotal > 0 ? round(($completedTasks / $expectedTotal) * 100, 1) : 0;
    }

    /**
     * 最近の活動取得
     */
    private function getRecentActivities($company)
    {
        return DB::table('audit_logs')
            ->where('company_id', $company->id)
            ->whereIn('event_type', ['task_completed', 'attendance_registered'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * 月間出席率計算
     */
    private function calculateMonthlyAttendanceRate($company)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();

        $students = $company->students()->where('status', 'active')->pluck('id');
        
        if ($students->isEmpty()) {
            return 0;
        }

        // 営業日数計算（土日除く）
        $businessDays = 0;
        $current = $startOfMonth->copy();
        while ($current <= $today) {
            if (!$current->isWeekend()) {
                $businessDays++;
            }
            $current->addDay();
        }

        if ($businessDays === 0) {
            return 0;
        }

        $expectedAttendances = $students->count() * $businessDays;
        
        $actualAttendances = Attendance::whereIn('user_id', $students)
            ->whereBetween('attendance_date', [$startOfMonth, $today])
            ->where('status', 'present')
            ->count();

        return round(($actualAttendances / $expectedAttendances) * 100, 1);
    }

    /**
     * 企業の課題進捗取得
     */
    private function getCompanyTaskProgress($company)
    {
        $tasks = Task::where(function ($q) use ($company) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->withCount(['submissions' => function ($q) use ($company) {
                $q->whereHas('user', function ($q2) use ($company) {
                    $q2->where('company_id', $company->id);
                });
            }])
            ->withCount(['submissions as completed_count' => function ($q) use ($company) {
                $q->where('status', 'completed')
                    ->whereHas('user', function ($q2) use ($company) {
                        $q2->where('company_id', $company->id);
                    });
            }])
            ->orderBy('display_order')
            ->get();

        return $tasks->map(function ($task) {
            $task->progress_rate = $task->submissions_count > 0 
                ? round(($task->completed_count / $task->submissions_count) * 100, 1)
                : 0;
            return $task;
        });
    }
}