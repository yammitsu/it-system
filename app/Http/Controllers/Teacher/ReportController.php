<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\AnalyticsService;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;
    protected $analyticsService;

    public function __construct(ReportService $reportService, AnalyticsService $analyticsService)
    {
        $this->reportService = $reportService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * レポート一覧画面
     */
    public function index()
    {
        $user = Auth::user();
        
        // 権限に応じたレポート一覧を取得
        $availableReports = $this->getAvailableReports($user);
        
        // 生成済みレポート履歴
        $generatedReports = $this->getGeneratedReports($user);

        return view('teacher.reports.index', compact('availableReports', 'generatedReports'));
    }

    /**
     * 分析ダッシュボード
     */
    public function analytics(Request $request)
    {
        $user = Auth::user();
        
        // 期間設定
        $startDate = $request->has('start_date') 
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subMonth();
        
        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // 企業取得
        $company = $user->role === 'system_admin' && $request->has('company_id')
            ? Company::find($request->company_id)
            : $user->company;

        if (!$company) {
            return back()->with('error', '企業が選択されていません。');
        }

        // 分析データ取得
        $analytics = $this->analyticsService->getCompanyAnalytics($company, $startDate, $endDate);

        // チャート用データ整形
        $chartData = $this->prepareChartData($analytics);

        return view('teacher.reports.analytics', compact(
            'analytics',
            'chartData',
            'company',
            'startDate',
            'endDate'
        ));
    }

    /**
     * 週次レポート生成
     */
    public function generateWeekly(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'week_start' => 'nullable|date',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $company = $this->getTargetCompany($user, $request->company_id);
        
        if (!$company) {
            return back()->with('error', '企業が選択されていません。');
        }

        $weekStart = $request->has('week_start')
            ? Carbon::parse($request->week_start)->startOfWeek()
            : Carbon::now()->startOfWeek();

        try {
            $report = $this->reportService->generateWeeklyReport($company, $weekStart);
            
            return response()->json([
                'success' => true,
                'message' => '週次レポートを生成しました。',
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'レポート生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 月次レポート生成
     */
    public function generateMonthly(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $company = $this->getTargetCompany($user, $request->company_id);
        
        if (!$company) {
            return back()->with('error', '企業が選択されていません。');
        }

        $month = $request->has('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : Carbon::now()->startOfMonth();

        try {
            $report = $this->reportService->generateMonthlyReport($company, $month);
            
            return response()->json([
                'success' => true,
                'message' => '月次レポートを生成しました。',
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'レポート生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 個人レポート生成
     */
    public function generateStudent(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $student = User::find($request->student_id);
        
        // 権限確認
        if (!$this->canAccessStudent($student)) {
            return response()->json([
                'success' => false,
                'message' => 'この受講生のレポートを生成する権限がありません。',
            ], 403);
        }

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subMonth();
        
        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        try {
            $report = $this->reportService->generateStudentReport($student, $startDate, $endDate);
            
            return response()->json([
                'success' => true,
                'message' => '個人レポートを生成しました。',
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'レポート生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * カスタムレポート生成
     */
    public function generateCustom(Request $request)
    {
        $request->validate([
            'type' => 'required|in:attendance,progress,performance,comparison',
            'format' => 'required|in:pdf,excel,json',
            'filters' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        // フィルターに権限制限を追加
        $filters = $request->filters ?? [];
        if ($user->role === 'company_admin') {
            $filters['company_id'] = $user->company_id;
        }

        try {
            $report = $this->reportService->generateCustomReport([
                'type' => $request->type,
                'format' => $request->format,
                'filters' => $filters,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'カスタムレポートを生成しました。',
                'report' => $report,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'レポート生成に失敗しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * レポートダウンロード
     */
    public function download(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;
        
        if (!Storage::exists($path)) {
            return back()->with('error', 'レポートファイルが見つかりません。');
        }

        return Storage::download($path);
    }

    /**
     * 利用可能なレポート取得
     */
    private function getAvailableReports(User $user)
    {
        $reports = [
            [
                'type' => 'weekly',
                'name' => '週次レポート',
                'description' => '週間の出席状況と進捗サマリー',
                'icon' => 'fas fa-calendar-week',
            ],
            [
                'type' => 'monthly',
                'name' => '月次レポート',
                'description' => '月間の詳細分析とパフォーマンス評価',
                'icon' => 'fas fa-calendar-alt',
            ],
        ];

        if ($user->isTeacher()) {
            $reports[] = [
                'type' => 'student',
                'name' => '個人レポート',
                'description' => '受講生個別の学習状況レポート',
                'icon' => 'fas fa-user-graduate',
            ];
        }

        if ($user->role === 'system_admin' || $user->role === 'company_admin') {
            $reports[] = [
                'type' => 'custom',
                'name' => 'カスタムレポート',
                'description' => '条件を指定して独自のレポートを生成',
                'icon' => 'fas fa-cog',
            ];
        }

        return $reports;
    }

    /**
     * 生成済みレポート取得
     */
    private function getGeneratedReports(User $user)
    {
        // ストレージから生成済みレポートのリストを取得
        $pattern = 'reports/*';
        
        if ($user->role === 'company_admin') {
            $pattern = "reports/*_{$user->company_id}_*";
        }

        $files = Storage::files($pattern);
        
        return collect($files)->map(function ($file) {
            $filename = basename($file);
            $parts = explode('_', $filename);
            
            return [
                'path' => $file,
                'filename' => $filename,
                'type' => $parts[0] ?? 'unknown',
                'created_at' => Storage::lastModified($file),
                'size' => Storage::size($file),
            ];
        })->sortByDesc('created_at')->take(20);
    }

    /**
     * 対象企業取得
     */
    private function getTargetCompany(User $user, $companyId = null)
    {
        if ($user->role === 'system_admin' && $companyId) {
            return Company::find($companyId);
        }

        return $user->company;
    }

    /**
     * 受講生アクセス権限確認
     */
    private function canAccessStudent(User $student)
    {
        $user = Auth::user();

        if ($user->role === 'system_admin') {
            return true;
        }

        if ($user->role === 'company_admin') {
            return $student->company_id === $user->company_id;
        }

        if ($user->role === 'teacher') {
            // 講師の担当企業の受講生か確認
            $companyIds = \DB::table('shifts')
                ->where('teacher_id', $user->id)
                ->distinct()
                ->pluck('company_id');
            
            return $companyIds->contains($student->company_id);
        }

        return false;
    }

    /**
     * チャートデータ準備
     */
    private function prepareChartData(array $analytics)
    {
        return [
            'progress_trends' => [
                'labels' => collect($analytics['progress_trends'])->pluck('week'),
                'datasets' => [
                    [
                        'label' => '完了数',
                        'data' => collect($analytics['progress_trends'])->pluck('completions'),
                        'borderColor' => 'rgb(75, 192, 192)',
                        'tension' => 0.1,
                    ],
                    [
                        'label' => 'アクティブ受講生',
                        'data' => collect($analytics['progress_trends'])->pluck('active_students'),
                        'borderColor' => 'rgb(255, 99, 132)',
                        'tension' => 0.1,
                    ],
                ],
            ],
            'attendance_patterns' => [
                'labels' => array_keys($analytics['attendance_patterns']['by_day_of_week']->toArray()),
                'datasets' => [
                    [
                        'label' => '出席率',
                        'data' => collect($analytics['attendance_patterns']['by_day_of_week'])->pluck('rate'),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                    ],
                ],
            ],
            'language_distribution' => [
                'labels' => collect($analytics['language_distribution'])->pluck('name'),
                'datasets' => [
                    [
                        'data' => collect($analytics['language_distribution'])->pluck('student_count'),
                        'backgroundColor' => [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                        ],
                    ],
                ],
            ],
        ];
    }
}