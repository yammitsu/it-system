<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Language;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class StudentController extends Controller
{
    /**
     * 受講生一覧
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = User::with(['company', 'language'])
            ->where('role', 'student');

        // 権限によるフィルタリング
        if ($user->role === 'company_admin') {
            $query->where('company_id', $user->company_id);
        } elseif ($user->role === 'teacher') {
            // 講師の場合は担当企業の受講生
            $companyIds = DB::table('shifts')
                ->where('teacher_id', $user->id)
                ->distinct()
                ->pluck('company_id');
            $query->whereIn('company_id', $companyIds);
        }

        // フィルター処理
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('language_id')) {
            $query->where('language_id', $request->language_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        // 2週間未出席フィルター
        if ($request->boolean('absent_two_weeks')) {
            $twoWeeksAgo = Carbon::now()->subWeeks(2);
            $query->whereDoesntHave('attendances', function ($q) use ($twoWeeksAgo) {
                $q->where('attendance_date', '>=', $twoWeeksAgo)
                    ->where('status', 'present');
            });
        }

        $students = $query->orderBy('name')->paginate(20);

        // 各受講生の統計情報を追加
        foreach ($students as $student) {
            $student->statistics = $this->getStudentStatistics($student);
        }

        // フィルター用データ
        $companies = Company::active()->get();
        $languages = Language::active()->get();

        return view('teacher.students.index', compact('students', 'companies', 'languages'));
    }

    /**
     * 受講生詳細
     */
    public function show(User $user)
    {
        // 権限確認
        if (!$this->canAccessStudent($user)) {
            abort(403, 'この受講生の情報にアクセスする権限がありません。');
        }

        $user->load(['company', 'language']);

        // 進捗情報
        $taskProgress = $this->getTaskProgress($user);

        // 出席情報
        $attendanceInfo = $this->getAttendanceInfo($user);

        // 最近の活動
        $recentActivities = $this->getRecentActivities($user);

        // 学習統計
        $learningStats = $this->getLearningStats($user);

        return view('teacher.students.show', compact(
            'user',
            'taskProgress',
            'attendanceInfo',
            'recentActivities',
            'learningStats'
        ));
    }

    /**
     * 受講生の進捗詳細
     */
    public function progress(User $user)
    {
        // 権限確認
        if (!$this->canAccessStudent($user)) {
            abort(403, 'この受講生の情報にアクセスする権限がありません。');
        }

        // 全課題と提出状況
        $tasks = Task::with(['submissions' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->where('language_id', $user->language_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->ordered()
            ->get();

        // カテゴリ別に整理
        $tasksByCategory = $tasks->groupBy('category_major');

        // 進捗サマリー
        $summary = [
            'total' => $tasks->count(),
            'completed' => $tasks->filter(function ($task) {
                return $task->submissions->first() && 
                       $task->submissions->first()->status === 'completed';
            })->count(),
            'in_progress' => $tasks->filter(function ($task) {
                return $task->submissions->first() && 
                       $task->submissions->first()->status === 'in_progress';
            })->count(),
        ];

        $summary['not_started'] = $summary['total'] - $summary['completed'] - $summary['in_progress'];
        $summary['completion_rate'] = $summary['total'] > 0 
            ? round(($summary['completed'] / $summary['total']) * 100, 1) 
            : 0;

        return view('teacher.students.progress', compact('user', 'tasksByCategory', 'summary'));
    }

    /**
     * Excelインポート
     */
    public function import(Request $request)
    {
        $user = Auth::user();

        // 権限確認
        if (!in_array($user->role, ['company_admin', 'system_admin'])) {
            return back()->with('error', '受講生の一括登録権限がありません。');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        DB::beginTransaction();
        try {
            // Excelファイル読み込み（実装は省略）
            // A1: 企業名
            // A3以降: 名前 | B: メール | C: 言語
            
            // ダミー実装
            $imported = 0;
            
            DB::commit();
            
            return back()->with('success', "{$imported}名の受講生を登録しました。");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'インポートに失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Excelエクスポート
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        $query = User::with(['company', 'language'])
            ->where('role', 'student');

        // 権限によるフィルタリング
        if ($user->role === 'company_admin') {
            $query->where('company_id', $user->company_id);
        }

        $students = $query->get();

        // Excel生成（実装は省略）
        // 実際にはExcelエクスポート用のExportクラスを作成

        return response()->download('students.xlsx');
    }

    /**
     * 受講生へのアクセス権限確認
     */
    private function canAccessStudent(User $student): bool
    {
        $user = Auth::user();

        if ($student->role !== 'student') {
            return false;
        }

        if ($user->role === 'system_admin') {
            return true;
        }

        if ($user->role === 'company_admin') {
            return $student->company_id === $user->company_id;
        }

        if ($user->role === 'teacher') {
            // 講師の場合は担当企業の受講生
            $companyIds = DB::table('shifts')
                ->where('teacher_id', $user->id)
                ->distinct()
                ->pluck('company_id');
            return $companyIds->contains($student->company_id);
        }

        return false;
    }

    /**
     * 受講生統計取得
     */
    private function getStudentStatistics(User $student): array
    {
        // 課題進捗
        $totalTasks = Task::where('language_id', $student->language_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $student->company_id);
            })
            ->count();

        $completedTasks = TaskSubmission::where('user_id', $student->id)
            ->where('status', 'completed')
            ->count();

        // 今月の出席率
        $startOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();
        
        $businessDays = 0;
        $current = $startOfMonth->copy();
        while ($current <= $today) {
            if (!$current->isWeekend()) {
                $businessDays++;
            }
            $current->addDay();
        }

        $presentDays = Attendance::where('user_id', $student->id)
            ->whereBetween('attendance_date', [$startOfMonth, $today])
            ->where('status', 'present')
            ->count();

        $attendanceRate = $businessDays > 0 ? round(($presentDays / $businessDays) * 100, 1) : 0;

        // 最終ログイン
        $lastLogin = $student->last_login_at ? Carbon::parse($student->last_login_at)->diffForHumans() : '未ログイン';

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            'attendance_rate' => $attendanceRate,
            'last_login' => $lastLogin,
            'study_hours' => round($student->total_study_hours / 60, 1),
        ];
    }

    /**
     * 課題進捗取得
     */
    private function getTaskProgress(User $student)
    {
        return Task::with(['submissions' => function ($q) use ($student) {
                $q->where('user_id', $student->id);
            }])
            ->where('language_id', $student->language_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $student->company_id);
            })
            ->ordered()
            ->get()
            ->map(function ($task) {
                $submission = $task->submissions->first();
                return [
                    'task' => $task,
                    'status' => $submission ? $submission->status : 'not_started',
                    'completed_at' => $submission ? $submission->completed_at : null,
                    'score' => $submission ? $submission->score : null,
                ];
            });
    }

    /**
     * 出席情報取得
     */
    private function getAttendanceInfo(User $student)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $attendances = Attendance::where('user_id', $student->id)
            ->where('attendance_date', '>=', $thirtyDaysAgo)
            ->orderBy('attendance_date', 'desc')
            ->get();

        $summary = [
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'cancelled' => $attendances->where('status', 'cancelled')->count(),
            'late' => $attendances->where('status', 'late')->count(),
        ];

        $lastAttendance = Attendance::where('user_id', $student->id)
            ->where('status', 'present')
            ->orderBy('attendance_date', 'desc')
            ->first();

        return [
            'recent' => $attendances,
            'summary' => $summary,
            'last_attendance' => $lastAttendance,
        ];
    }

    /**
     * 最近の活動取得
     */
    private function getRecentActivities(User $student)
    {
        $activities = [];

        // 課題提出
        $submissions = TaskSubmission::where('user_id', $student->id)
            ->with('task')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($submissions as $submission) {
            $activities[] = [
                'type' => 'submission',
                'title' => $submission->task->title,
                'status' => $submission->status,
                'date' => $submission->updated_at,
            ];
        }

        // 出席
        $attendances = Attendance::where('user_id', $student->id)
            ->orderBy('attendance_date', 'desc')
            ->limit(10)
            ->get();

        foreach ($attendances as $attendance) {
            $activities[] = [
                'type' => 'attendance',
                'title' => '出席登録',
                'status' => $attendance->status,
                'date' => $attendance->created_at,
            ];
        }

        // 日付でソート
        usort($activities, function ($a, $b) {
            return $b['date']->timestamp - $a['date']->timestamp;
        });

        return array_slice($activities, 0, 20);
    }

    /**
     * 学習統計取得
     */
    private function getLearningStats(User $student)
    {
        // 週別学習時間
        $weeklyStats = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();
            
            $minutes = Attendance::where('user_id', $student->id)
                ->whereBetween('attendance_date', [$weekStart, $weekEnd])
                ->sum('study_minutes');

            $weeklyStats[] = [
                'week' => $weekStart->format('m/d'),
                'hours' => round($minutes / 60, 1),
            ];
        }

        // カテゴリ別進捗
        $categoryStats = TaskSubmission::where('user_id', $student->id)
            ->join('tasks', 'task_submissions.task_id', '=', 'tasks.id')
            ->select('tasks.category_major', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN task_submissions.status = "completed" THEN 1 ELSE 0 END) as completed'))
            ->groupBy('tasks.category_major')
            ->get();

        return [
            'weekly' => $weeklyStats,
            'categories' => $categoryStats,
        ];
    }
}