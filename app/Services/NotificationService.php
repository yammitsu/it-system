<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $emailEnabled;
    protected $slackEnabled;
    protected $pushEnabled;
    protected $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
        $this->emailEnabled = $this->getSetting('notification', 'email_enabled', true);
        $this->slackEnabled = $this->getSetting('notification', 'slack_notification_enabled', true);
        $this->pushEnabled = $this->getSetting('notification', 'push_notification_enabled', false);
    }

    /**
     * 通知を送信
     */
    public function send(User $user, string $type, string $title, string $message, array $data = []): void
    {
        // データベースに通知を保存
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'channel' => 'in_app',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $this->getPriority($type),
        ]);

        // ユーザーの通知設定を確認
        $settings = $user->notification_settings ?? [];

        // メール通知
        if ($this->emailEnabled && ($settings['email'] ?? true)) {
            $this->sendEmail($user, $notification);
        }

        // Slack通知
        if ($this->slackEnabled && ($settings['slack'] ?? true) && $user->slack_user_id) {
            $this->sendSlack($user, $notification);
        }

        // プッシュ通知（実装は省略）
        if ($this->pushEnabled && ($settings['push'] ?? false)) {
            $this->sendPush($user, $notification);
        }
    }

    /**
     * 一括通知送信
     */
    public function sendBulk(array $userIds, string $type, string $title, string $message, array $data = []): void
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->send($user, $type, $title, $message, $data);
        }
    }

    /**
     * 全体通知
     */
    public function broadcast(string $type, string $title, string $message, array $data = []): void
    {
        $users = User::where('status', 'active')->get();

        foreach ($users as $user) {
            $this->send($user, $type, $title, $message, $data);
        }
    }

    /**
     * メール送信
     */
    protected function sendEmail(User $user, Notification $notification): void
    {
        try {
            // メール送信の実装（簡略版）
            // 実際にはMailableクラスを作成して送信
            Log::info('Email notification sent', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
            ]);

            $notification->update([
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Slack通知送信
     */
    protected function sendSlack(User $user, Notification $notification): void
    {
        try {
            $message = "*{$notification->title}*\n{$notification->message}";
            
            // DMでメッセージを送信
            $this->slackService->postMessage($user->slack_user_id, $message);

            Log::info('Slack notification sent', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * プッシュ通知送信（ダミー実装）
     */
    protected function sendPush(User $user, Notification $notification): void
    {
        // プッシュ通知の実装
        Log::info('Push notification would be sent', [
            'user_id' => $user->id,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * 通知の優先度を取得
     */
    protected function getPriority(string $type): string
    {
        $highPriority = ['attendance_reminder', 'task_deadline', 'warning'];
        $urgentPriority = ['system_alert', 'security_alert'];

        if (in_array($type, $urgentPriority)) {
            return 'urgent';
        }

        if (in_array($type, $highPriority)) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * システム設定取得
     */
    protected function getSetting(string $category, string $key, $default = null)
    {
        $setting = SystemSetting::where('category', $category)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        // 型に応じて値を変換
        switch ($setting->type) {
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $setting->value;
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }
}