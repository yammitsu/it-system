<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // ユーザーのロールが許可されているか確認
        if (!in_array($user->role, $roles)) {
            abort(403, 'アクセス権限がありません。');
        }

        // アカウントがアクティブか確認
        if ($user->status !== 'active') {
            Auth::logout();
            return redirect('/login')->withErrors(['account' => 'アカウントが無効です。']);
        }

        return $next($request);
    }
}

// app/Http/Middleware/CheckSession.php
namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $sessionId = session()->getId();

        // セッション確認
        $userSession = UserSession::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('is_active', true)
            ->first();

        if (!$userSession) {
            Auth::logout();
            return redirect('/login')->withErrors(['session' => 'セッションが無効です。']);
        }

        // セッション有効期限確認
        if ($userSession->expires_at < now()) {
            $userSession->update(['is_active' => false]);
            Auth::logout();
            return redirect('/login')->withErrors(['session' => 'セッションの有効期限が切れました。']);
        }

        // 最終アクティビティ更新
        $userSession->updateActivity();

        return $next($request);
    }
}

// app/Http/Middleware/CheckCompanyAccess.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCompanyAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // システム管理者は全アクセス可能
        if ($user->role === 'system_admin') {
            return $next($request);
        }

        // 企業所属確認
        if (!$user->company_id) {
            abort(403, '企業に所属していません。');
        }

        // 企業がアクティブか確認
        if (!$user->company->isActive()) {
            abort(403, '所属企業の契約が無効です。');
        }

        // パラメータに企業IDがある場合、アクセス権限確認
        if ($request->route('company_id')) {
            $requestedCompanyId = $request->route('company_id');
            
            if ($user->company_id != $requestedCompanyId) {
                abort(403, '他社のデータにはアクセスできません。');
            }
        }

        return $next($request);
    }
}

// app/Http/Middleware/PreventMultipleLogins.php
namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreventMultipleLogins
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $currentSessionId = session()->getId();

        // 他のアクティブセッションを確認
        $otherActiveSessions = UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();

        if ($otherActiveSessions) {
            // 設定で複数ログインが許可されているか確認
            $allowMultiple = config('auth.session.allow_multiple', false);
            
            if (!$allowMultiple) {
                Auth::logout();
                return redirect('/login')->withErrors(['session' => '別の端末でログイン中です。']);
            }
        }

        return $next($request);
    }
}

// app/Http/Middleware/LogActivity.php
namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // POSTリクエストの場合のみログ記録
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('delete')) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * アクティビティログ記録
     */
    protected function logActivity(Request $request, $response)
    {
        $user = Auth::user();
        
        // センシティブな情報を除外
        $requestData = $request->except(['password', 'password_confirmation', '_token']);

        AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'company_id' => $user ? $user->company_id : null,
            'event_type' => 'http_request',
            'action' => $request->method(),
            'description' => $this->getActionDescription($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_data' => $requestData,
            'response_code' => $response->getStatusCode(),
            'execution_time' => microtime(true) - LARAVEL_START,
        ]);
    }

    /**
     * アクション説明取得
     */
    protected function getActionDescription(Request $request): string
    {
        $route = $request->route();
        
        if ($route) {
            $action = $route->getActionName();
            $name = $route->getName();
            
            if ($name) {
                return "Route: {$name}";
            }
            
            return "Action: {$action}";
        }

        return "URL: {$request->path()}";
    }
}