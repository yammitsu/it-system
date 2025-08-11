<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TaskRequest;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Language;
use App\Models\Company;
use App\Models\AuditLog;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * 課題一覧表示
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Task::with(['language', 'company', 'creator'])
            ->withCount('submissions');

        // 企業管理者の場合、自社の課題のみ
        if ($user->role === 'company_admin') {
            $query->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            });
        }

        // フィルタリング
        if ($request->filled('language_id')) {
            $query->where('language_id', $request->language_id);
        }

        if ($request->filled('category')) {
            $query->where('category_major', $request->category);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tasks = $query->ordered()->paginate(20);

        $languages = Language::active()->get();
        $categories = Task::distinct()->pluck('category_major');

        return view('teacher.tasks.index', compact('tasks', 'languages', 'categories'));
    }

    /**
     * 課題作成画面
     */
    public function create()
    {
        $languages = Language::active()->get();
        $companies = Company::active()->get();
        $parentTasks = Task::whereNull('company_id')->ordered()->get();

        return view('teacher.tasks.create', compact('languages', 'companies', 'parentTasks'));
    }

    /**
     * 課題保存処理
     */
    public function store(TaskRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['created_by'] = Auth::id();

            // ファイルアップロード処理
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $uploadResult = $this->fileService->uploadTaskFile($file, $data['language_id']);
                
                $data['file_path'] = $uploadResult['path'];
                $data['file_name'] = $uploadResult['name'];
                $data['file_size'] = $uploadResult['size'];
                $data['file_mime_type'] = $uploadResult['mime_type'];
                $data['file_scanned_at'] = now();
                $data['file_is_safe'] = true;
            }

            // タグ処理
            if ($request->filled('tags')) {
                $data['tags'] = explode(',', $request->tags);
            }

            $task = Task::create($data);

            // 依存関係設定
            if ($request->filled('dependencies')) {
                foreach ($request->dependencies as $dependencyId) {
                    $task->dependencies()->attach($dependencyId, [
                        'dependency_type' => 'required',
                    ]);
                }
            }

            // 監査ログ
            AuditLog::log([
                'event_type' => 'task_created',
                'model_type' => 'Task',
                'model_id' => $task->id,
                'action' => 'create',
                'description' => "課題を作成: {$task->title}",
                'new_values' => $task->toArray(),
            ]);

            DB::commit();

            return redirect()->route('teacher.tasks.show', $task)
                ->with('success', '課題を作成しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', '課題の作成に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * 課題詳細表示
     */
    public function show(Task $task)
    {
        $task->load(['language', 'company', 'creator', 'dependencies', 'submissions.user']);

        // 提出状況の統計
        $submissionStats = TaskSubmission::where('task_id', $task->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // 最近の提出
        $recentSubmissions = $task->submissions()
            ->with('user')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return view('teacher.tasks.show', compact('task', 'submissionStats', 'recentSubmissions'));
    }

    /**
     * 課題編集画面
     */
    public function edit(Task $task)
    {
        $this->authorize('update', $task);

        $languages = Language::active()->get();
        $companies = Company::active()->get();
        $parentTasks = Task::whereNull('company_id')
            ->where('id', '!=', $task->id)
            ->ordered()
            ->get();

        $task->load('dependencies');

        return view('teacher.tasks.edit', compact('task', 'languages', 'companies', 'parentTasks'));
    }

    /**
     * 課題更新処理
     */
    public function update(TaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        DB::beginTransaction();
        try {
            $data = $request->validated();

            // ファイルアップロード処理
            if ($request->hasFile('file')) {
                // 古いファイル削除
                if ($task->file_path && Storage::exists($task->file_path)) {
                    Storage::delete($task->file_path);
                }

                $file = $request->file('file');
                $uploadResult = $this->fileService->uploadTaskFile($file, $data['language_id']);
                
                $data['file_path'] = $uploadResult['path'];
                $data['file_name'] = $uploadResult['name'];
                $data['file_size'] = $uploadResult['size'];
                $data['file_mime_type'] = $uploadResult['mime_type'];
                $data['file_scanned_at'] = now();
                $data['file_is_safe'] = true;
            }

            // タグ処理
            if ($request->filled('tags')) {
                $data['tags'] = explode(',', $request->tags);
            }

            $oldValues = $task->toArray();
            $task->update($data);

            // 依存関係更新
            if ($request->has('dependencies')) {
                $task->dependencies()->sync($request->dependencies);
            }

            // 監査ログ
            AuditLog::log([
                'event_type' => 'task_updated',
                'model_type' => 'Task',
                'model_id' => $task->id,
                'action' => 'update',
                'description' => "課題を更新: {$task->title}",
                'old_values' => $oldValues,
                'new_values' => $task->toArray(),
            ]);

            DB::commit();

            return redirect()->route('teacher.tasks.show', $task)
                ->with('success', '課題を更新しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', '課題の更新に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * 課題削除処理
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        // 提出がある場合は削除不可
        if ($task->submissions()->exists()) {
            return back()->with('error', '提出がある課題は削除できません。');
        }

        DB::beginTransaction();
        try {
            // ファイル削除
            if ($task->file_path && Storage::exists($task->file_path)) {
                Storage::delete($task->file_path);
            }

            // 監査ログ
            AuditLog::log([
                'event_type' => 'task_deleted',
                'model_type' => 'Task',
                'model_id' => $task->id,
                'action' => 'delete',
                'description' => "課題を削除: {$task->title}",
                'old_values' => $task->toArray(),
            ]);

            $task->delete();

            DB::commit();

            return redirect()->route('teacher.tasks.index')
                ->with('success', '課題を削除しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', '課題の削除に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * 提出一覧
     */
    public function submissions(Task $task)
    {
        $submissions = $task->submissions()
            ->with(['user', 'evaluator'])
            ->latest('updated_at')
            ->paginate(20);

        return view('teacher.tasks.submissions', compact('task', 'submissions'));
    }

    /**
     * 提出評価
     */
    public function evaluate(Request $request, Task $task, TaskSubmission $submission)
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'required|string|max:2000',
            'evaluation_comment' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'evaluation_comment' => $request->evaluation_comment,
            'evaluated_by' => Auth::id(),
            'evaluated_at' => now(),
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'submission_evaluated',
            'model_type' => 'TaskSubmission',
            'model_id' => $submission->id,
            'action' => 'evaluate',
            'description' => "課題を評価: {$task->title} - {$submission->user->name}",
        ]);

        return back()->with('success', '評価を保存しました。');
    }
}