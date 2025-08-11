<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\FileDownloadLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /**
     * 課題一覧表示
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // ユーザーの言語に対応する課題を取得
        $query = Task::with(['language', 'submissions' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->where('language_id', $user->language_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->available()
            ->ordered();

        // カテゴリフィルタ
        if ($request->filled('category')) {
            $query->where('category_major', $request->category);
        }

        // 難易度フィルタ
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // ステータスフィルタ
        if ($request->filled('status')) {
            $status = $request->status;
            $query->whereHas('submissions', function ($q) use ($user, $status) {
                $q->where('user_id', $user->id)
                    ->where('status', $status);
            }, $status === 'not_started' ? '=' : '>', 0);
        }

        $tasks = $query->paginate(20);

        // 進捗統計を計算
        $stats = $this->calculateProgressStats($user);

        return view('student.tasks.index', compact('tasks', 'stats'));
    }

    /**
     * 課題詳細表示
     */
    public function show(Task $task)
    {
        $user = Auth::user();

        // アクセス権限確認
        if (!$this->canAccessTask($task, $user)) {
            abort(403, 'この課題にアクセスする権限がありません。');
        }

        // 前提条件確認
        if (!$task->hasCompletedPrerequisites($user)) {
            return redirect()->route('student.tasks.index')
                ->with('warning', '前提となる課題を先に完了してください。');
        }

        // ユーザーの提出情報取得
        $submission = $task->getUserSubmission($user);

        // 依存関係取得
        $dependencies = $task->dependencies()->with('submissions', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->get();

        // 他の受講生の進捗（同じ企業のみ）
        $peerProgress = TaskSubmission::where('task_id', $task->id)
            ->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->where('id', '!=', $user->id);
            })
            ->select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return view('student.tasks.show', compact('task', 'submission', 'dependencies', 'peerProgress'));
    }

    /**
     * 課題ファイルダウンロード
     */
    public function download(Task $task)
    {
        $user = Auth::user();

        // アクセス権限確認
        if (!$this->canAccessTask($task, $user)) {
            abort(403, 'この課題にアクセスする権限がありません。');
        }

        // ファイル存在確認
        if (!$task->file_path || !Storage::exists($task->file_path)) {
            return back()->with('error', 'ファイルが見つかりません。');
        }

        // 提出情報取得または作成
        $submission = TaskSubmission::firstOrCreate(
            [
                'user_id' => $user->id,
                'task_id' => $task->id,
            ],
            [
                'status' => 'not_started',
                'first_downloaded_at' => now(),
            ]
        );

        // 初回ダウンロード時の処理
        if (!$submission->first_downloaded_at) {
            $submission->update([
                'first_downloaded_at' => now(),
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }

        // ダウンロード回数更新
        $submission->increment('download_count');

        // ダウンロードログ記録
        FileDownloadLog::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'file_name' => $task->file_name,
            'file_path' => $task->file_path,
            'file_size' => $task->file_size,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'file_download',
            'model_type' => 'Task',
            'model_id' => $task->id,
            'action' => 'download',
            'description' => "課題ファイルをダウンロード: {$task->title}",
        ]);

        return Storage::download($task->file_path, $task->file_name);
    }

    /**
     * 課題完了処理
     */
    public function complete(Request $request, Task $task)
    {
        $user = Auth::user();

        // アクセス権限確認
        if (!$this->canAccessTask($task, $user)) {
            abort(403, 'この課題にアクセスする権限がありません。');
        }

        $request->validate([
            'completion_note' => 'nullable|string|max:1000',
            'actual_hours' => 'nullable|integer|min:1|max:999',
        ]);

        // 提出情報取得
        $submission = TaskSubmission::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->first();

        if (!$submission) {
            return back()->with('error', 'まず課題をダウンロードしてください。');
        }

        if ($submission->status === 'completed') {
            return back()->with('info', 'この課題は既に完了しています。');
        }

        // 完了処理
        $submission->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_note' => $request->completion_note,
            'actual_hours' => $request->actual_hours ?? ($task->estimated_hours * 60),
        ]);

        // 課題の統計更新
        $task->updateStatistics();

        // 監査ログ
        AuditLog::log([
            'event_type' => 'task_completed',
            'model_type' => 'Task',
            'model_id' => $task->id,
            'action' => 'complete',
            'description' => "課題を完了: {$task->title}",
        ]);

        return redirect()->route('student.tasks.show', $task)
            ->with('success', '課題を完了しました！');
    }

    /**
     * 進捗更新
     */
    public function updateProgress(Request $request, Task $task)
    {
        $user = Auth::user();

        $request->validate([
            'progress_comment' => 'required|string|max:1000',
        ]);

        $submission = TaskSubmission::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->first();

        if (!$submission) {
            return back()->with('error', '課題が開始されていません。');
        }

        $submission->update([
            'progress_comment' => $request->progress_comment,
        ]);

        return back()->with('success', '進捗を更新しました。');
    }

    /**
     * 課題アクセス権限確認
     */
    protected function canAccessTask(Task $task, $user): bool
    {
        // 言語が一致しない場合
        if ($task->language_id !== $user->language_id) {
            return false;
        }

        // 企業専用課題の場合
        if ($task->company_id && $task->company_id !== $user->company_id) {
            return false;
        }

        // 課題が利用可能か
        if (!$task->isAvailable()) {
            return false;
        }

        return true;
    }

    /**
     * 進捗統計計算
     */
    protected function calculateProgressStats($user): array
    {
        $submissions = TaskSubmission::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalTasks = Task::where('language_id', $user->language_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->available()
            ->count();

        $completed = $submissions['completed'] ?? 0;
        $inProgress = $submissions['in_progress'] ?? 0;
        $notStarted = $totalTasks - $completed - $inProgress;

        return [
            'total' => $totalTasks,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'completion_rate' => $totalTasks > 0 ? round(($completed / $totalTasks) * 100, 1) : 0,
        ];
    }
}