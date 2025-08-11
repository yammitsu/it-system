<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UserSession;
use App\Models\AuditLog;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    protected $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
    }

    /**
     * ログイン画面表示
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect($this->redirectPath());
        }

        return view('auth.login');
    }

    /**
     * ログイン処理
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // ユーザー取得
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return $this->sendFailedLoginResponse($request, 'メールアドレスまたはパスワードが正しくありません。');
        }

        // アカウントステータス確認
        if ($user->status !== 'active') {
            return $this->sendFailedLoginResponse($request, 'このアカウントは利用できません。管理者にお問い合わせください。');
        }

        // Slack連携確認（必須の場合）
        if (!$user->slack_user_id && $this->isSlackRequired($user)) {
            // Slack登録確認
            $slackUser = $this->slackService->findUserByEmail($user->slack_email ?? $user->email);
            if (!$slackUser) {
                return $this->sendFailedLoginResponse($request, 'Slackワークスペースへの参加が必要です。');
            }
            
            // Slack情報を更新
            $user->update([
                'slack_user_id' => $slackUser['id'],
                'slack_email' => $slackUser['profile']['email'] ?? $user->email,
            ]);
        }

        // パスワード確認
        if (!Hash::check($credentials['password'], $user->password)) {
            $this->recordFailedLogin($user, $request);
            return $this->sendFailedLoginResponse($request, 'メールアドレスまたはパスワードが正しくありません。');
        }

        // 多重ログイン対策
        if (!$this->checkMultipleSession($user)) {
            return $this->sendFailedLoginResponse($request, '別の端末でログイン中です。ログアウトしてから再度お試しください。');
        }

        // ログイン実行
        Auth::login($user, $request->boolean('remember'));

        // セッション作成
        $this->createUserSession($user, $request);

        // ログイン情報更新
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // 監査ログ記録
        AuditLog::log([
            'event_type' => 'login',
            'action' => 'login',
            'description' => 'ユーザーがログインしました',
        ]);

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPath());
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // セッション無効化
            UserSession::where('user_id', $user->id)
                ->where('session_id', session()->getId())
                ->update(['is_active' => false]);

            // 監査ログ記録
            AuditLog::log([
                'event_type' => 'logout',
                'action' => 'logout',
                'description' => 'ユーザーがログアウトしました',
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * リダイレクト先パス取得
     */
    protected function redirectPath()
    {
        $user = Auth::user();

        switch ($user->role) {
            case 'system_admin':
                return '/admin/dashboard';
            case 'company_admin':
                return '/company/dashboard';
            case 'teacher':
                return '/teacher/dashboard';
            case 'student':
                return '/student/dashboard';
            default:
                return '/';
        }
    }

    /**
     * Slack連携が必須か確認
     */
    protected function isSlackRequired(User $user): bool
    {
        // システム管理者は除外
        if ($user->role === 'system_admin') {
            return false;
        }

        // 企業のSlack設定確認
        if ($user->company && $user->company->slack_workspace_id) {
            return true;
        }

        return false;
    }

    /**
     * 多重セッション確認
     */
    protected function checkMultipleSession(User $user): bool
    {
        $allowMultiple = config('auth.session.allow_multiple', false);

        if ($allowMultiple) {
            return true;
        }

        // アクティブセッション確認
        $activeSession = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($activeSession) {
            return false;
        }

        return true;
    }

    /**
     * ユーザーセッション作成
     */
    protected function createUserSession(User $user, Request $request)
    {
        // 既存のアクティブセッションを無効化
        UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // 新規セッション作成
        UserSession::create([
            'user_id' => $user->id,
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->detectDevice($request->userAgent()),
            'browser' => $this->detectBrowser($request->userAgent()),
            'platform' => $this->detectPlatform($request->userAgent()),
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(config('auth.session.lifetime', 120)),
            'is_active' => true,
        ]);
    }

    /**
     * ログイン失敗記録
     */
    protected function recordFailedLogin(User $user, Request $request)
    {
        AuditLog::log([
            'user_id' => $user->id,
            'event_type' => 'login_failed',
            'action' => 'login',
            'description' => 'ログイン失敗',
            'metadata' => [
                'email' => $user->email,
                'ip' => $request->ip(),
            ],
        ]);
    }

    /**
     * ログイン失敗レスポンス
     */
    protected function sendFailedLoginResponse(Request $request, string $message)
    {
        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => $message]);
    }

    /**
     * デバイス検出
     */
    protected function detectDevice($userAgent): string
    {
        if (preg_match('/mobile/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * ブラウザ検出
     */
    protected function detectBrowser($userAgent): string
    {
        if (preg_match('/firefox/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/chrome/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/safari/i', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/edge/i', $userAgent)) {
            return 'Edge';
        }
        return 'Other';
    }

    /**
     * プラットフォーム検出
     */
    protected function detectPlatform($userAgent): string
    {
        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/mac/i', $userAgent)) {
            return 'MacOS';
        }
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }
        if (preg_match('/ios|iphone|ipad/i', $userAgent)) {
            return 'iOS';
        }
        return 'Other';
    }
}