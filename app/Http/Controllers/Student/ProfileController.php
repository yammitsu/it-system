<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\AuditLog;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    protected $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
    }

    /**
     * プロフィール画面表示
     */
    public function index()
    {
        $user = Auth::user();
        $user->load(['company', 'language']);

        // 学習統計
        $stats = [
            'total_study_hours' => round($user->total_study_hours / 60, 1),
            'consecutive_days' => $user->consecutive_days,
            'completed_tasks' => $user->taskSubmissions()->where('status', 'completed')->count(),
            'average_score' => $user->taskSubmissions()->whereNotNull('score')->avg('score'),
        ];

        // バッジ
        $badges = $user->badges()->orderBy('earned_at', 'desc')->get();

        return view('student.profile.index', compact('user', 'stats', 'badges'));
    }

    /**
     * プロフィール更新
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'timezone' => 'required|string',
            'locale' => 'required|string',
        ]);

        $oldEmail = $user->email;
        $newEmail = $request->email;

        // メールアドレス変更時のSlack確認
        if ($oldEmail !== $newEmail) {
            // Slack参加確認
            if ($user->company && $user->company->slack_workspace_id) {
                $slackUser = $this->slackService->findUserByEmail($newEmail);
                
                if (!$slackUser) {
                    return back()->with('error', '新しいメールアドレスがSlackワークスペースに登録されていません。先にSlackに参加してください。');
                }

                // Slack情報を更新
                $user->slack_user_id = $slackUser['id'];
                $user->slack_email = $newEmail;
            }
        }

        // プロフィール更新
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $request->department,
            'timezone' => $request->timezone,
            'locale' => $request->locale,
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'profile_updated',
            'model_type' => 'User',
            'model_id' => $user->id,
            'action' => 'update',
            'description' => 'プロフィールを更新しました',
        ]);

        return back()->with('success', 'プロフィールを更新しました。');
    }

    /**
     * パスワード更新
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = Auth::user();

        // パスワード更新
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'password_changed',
            'model_type' => 'User',
            'model_id' => $user->id,
            'action' => 'update',
            'description' => 'パスワードを変更しました',
        ]);

        return back()->with('success', 'パスワードを更新しました。');
    }

    /**
     * 通知設定更新
     */
    public function updateNotificationSettings(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email_notification' => 'boolean',
            'slack_notification' => 'boolean',
            'in_app_notification' => 'boolean',
        ]);

        $settings = [
            'email' => $request->boolean('email_notification'),
            'slack' => $request->boolean('slack_notification'),
            'in_app' => $request->boolean('in_app_notification'),
        ];

        $user->update([
            'notification_settings' => $settings,
        ]);

        return back()->with('success', '通知設定を更新しました。');
    }
}