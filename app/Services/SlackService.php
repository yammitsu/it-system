<?php

namespace App\Services;

use App\Models\User;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\SlackChannel;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Exception;

class SlackService
{
    protected $client;
    protected $token;
    protected $workspaceUrl;
    protected $channelPrefix;
    protected $enabled;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://slack.com/api/',
            'timeout' => 30,
        ]);

        $this->token = $this->getSetting('slack', 'bot_token');
        $this->workspaceUrl = $this->getSetting('slack', 'workspace_url');
        $this->channelPrefix = $this->getSetting('slack', 'channel_prefix', 'training-');
        $this->enabled = (bool) $this->getSetting('slack', 'enabled', true);
    }

    /**
     * Slack連携が有効か確認
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->token);
    }

    /**
     * ユーザーをメールアドレスで検索
     */
    public function findUserByEmail(string $email): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $response = $this->client->post('users.lookupByEmail', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => [
                    'email' => $email,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['ok']) {
                return $data['user'];
            }

            Log::warning('Slack user not found', ['email' => $email, 'error' => $data['error'] ?? 'Unknown']);
            return null;

        } catch (RequestException $e) {
            Log::error('Slack API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * チャンネル作成
     */
    public function createChannel(string $name, string $description = ''): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            // チャンネル名の正規化（Slackの制限に対応）
            $channelName = $this->normalizeChannelName($name);

            $response = $this->client->post('conversations.create', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => [
                    'name' => $channelName,
                    'is_private' => false,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['ok']) {
                $channel = $data['channel'];

                // チャンネルの説明を設定
                if ($description) {
                    $this->setChannelTopic($channel['id'], $description);
                }

                // 初回メッセージ投稿
                $this->postWelcomeMessage($channel['id']);

                Log::info('Slack channel created', ['channel' => $channelName]);
                return $channel;
            }

            Log::warning('Failed to create Slack channel', ['name' => $channelName, 'error' => $data['error'] ?? 'Unknown']);
            return null;

        } catch (RequestException $e) {
            Log::error('Slack API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * チャンネルにユーザーを招待
     */
    public function inviteToChannel(string $channelId, array $userIds): bool
    {
        if (!$this->isEnabled() || empty($userIds)) {
            return false;
        }

        try {
            $response = $this->client->post('conversations.invite', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => [
                    'channel' => $channelId,
                    'users' => implode(',', $userIds),
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['ok']) {
                Log::info('Users invited to Slack channel', [
                    'channel' => $channelId,
                    'users' => $userIds,
                ]);
                return true;
            }

            Log::warning('Failed to invite users to Slack channel', [
                'channel' => $channelId,
                'error' => $data['error'] ?? 'Unknown',
            ]);
            return false;

        } catch (RequestException $e) {
            Log::error('Slack API error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * チャンネルからユーザーを削除
     */
    public function removeFromChannel(string $channelId, string $userId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $response = $this->client->post('conversations.kick', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => [
                    'channel' => $channelId,
                    'user' => $userId,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['ok']) {
                Log::info('User removed from Slack channel', [
                    'channel' => $channelId,
                    'user' => $userId,
                ]);
                return true;
            }

            Log::warning('Failed to remove user from Slack channel', [
                'channel' => $channelId,
                'user' => $userId,
                'error' => $data['error'] ?? 'Unknown',
            ]);
            return false;

        } catch (RequestException $e) {
            Log::error('Slack API error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * メッセージ投稿
     */
    public function postMessage(string $channelId, string $text, array $attachments = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $params = [
                'channel' => $channelId,
                'text' => $text,
                'as_user' => true,
            ];

            if (!empty($attachments)) {
                $params['attachments'] = json_encode($attachments);
            }

            $response = $this->client->post('chat.postMessage', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['ok']) {
                Log::info('Message posted to Slack', ['channel' => $channelId]);
                return true;
            }

            Log::warning('Failed to post message to Slack', [
                'channel' => $channelId,
                'error' => $data['error'] ?? 'Unknown',
            ]);
            return false;

        } catch (RequestException $e) {
            Log::error('Slack API error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 日次チャンネル作成
     */
    public function createDailyChannel(\DateTime $date, Shift $shift): ?SlackChannel
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $channelName = $this->generateDailyChannelName($date, $shift);
        $description = $this->generateChannelDescription($date, $shift);

        $channelData = $this->createChannel($channelName, $description);

        if (!$channelData) {
            return null;
        }

        // データベースに記録
        $slackChannel = SlackChannel::create([
            'channel_id' => $channelData['id'],
            'channel_name' => $channelData['name'],
            'date' => $date->format('Y-m-d'),
            'shift_id' => $shift->id,
            'type' => 'daily',
            'is_archived' => false,
            'created_at_slack' => now(),
            'members' => [],
        ]);

        // シフトを更新
        $shift->update([
            'slack_channel_id' => $channelData['id'],
            'slack_channel_created' => true,
        ]);

        return $slackChannel;
    }

    /**
     * 出席者をチャンネルに招待
     */
    public function inviteAttendees(string $channelId, \DateTime $date): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        // 出席予定者を取得
        $attendees = Attendance::where('attendance_date', $date->format('Y-m-d'))
            ->where('status', 'present')
            ->where('slack_invited', false)
            ->whereHas('user', function ($q) {
                $q->whereNotNull('slack_user_id');
            })
            ->with('user')
            ->get();

        $invitedCount = 0;
        $userIds = [];

        foreach ($attendees as $attendance) {
            $userIds[] = $attendance->user->slack_user_id;
        }

        if (!empty($userIds)) {
            // バッチで招待（Slack APIの制限に対応）
            $chunks = array_chunk($userIds, 30); // 30人ずつ処理

            foreach ($chunks as $chunk) {
                if ($this->inviteToChannel($channelId, $chunk)) {
                    $invitedCount += count($chunk);
                }
            }

            // 招待済みフラグ更新
            Attendance::where('attendance_date', $date->format('Y-m-d'))
                ->where('status', 'present')
                ->whereIn('user_id', $attendees->pluck('user_id'))
                ->update([
                    'slack_channel_id' => $channelId,
                    'slack_invited' => true,
                    'slack_invited_at' => now(),
                ]);
        }

        return $invitedCount;
    }

    /**
     * チャンネル名生成
     */
    protected function generateDailyChannelName(\DateTime $date, Shift $shift): string
    {
        $dateStr = $date->format('Ymd');
        $language = $shift->language ? $shift->language->code : 'general';
        
        return $this->channelPrefix . $dateStr . '-' . $language;
    }

    /**
     * チャンネル説明生成
     */
    protected function generateChannelDescription(\DateTime $date, Shift $shift): string
    {
        $dateStr = $date->format('Y年m月d日');
        $teacher = $shift->teacher->name;
        $language = $shift->language ? $shift->language->name : '全般';

        return "{$dateStr} {$language}講習 (講師: {$teacher})";
    }

    /**
     * チャンネル名正規化
     */
    protected function normalizeChannelName(string $name): string
    {
        // Slackのチャンネル名制限に合わせて正規化
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\-_]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');
        
        // 最大21文字に制限
        if (strlen($name) > 21) {
            $name = substr($name, 0, 21);
        }

        return $name;
    }

    /**
     * チャンネルトピック設定
     */
    protected function setChannelTopic(string $channelId, string $topic): bool
    {
        try {
            $response = $this->client->post('conversations.setTopic', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'form_params' => [
                    'channel' => $channelId,
                    'topic' => $topic,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['ok'] ?? false;

        } catch (RequestException $e) {
            Log::error('Failed to set channel topic', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * ウェルカムメッセージ投稿
     */
    protected function postWelcomeMessage(string $channelId): void
    {
        $message = "このチャンネルへようこそ！\n\n";
        $message .= "【チャンネル利用ガイド】\n";
        $message .= "• 講習に関する質問や情報共有にご利用ください\n";
        $message .= "• 講師への質問は @mention を使ってお知らせください\n";
        $message .= "• 技術的な議論は積極的に行いましょう\n";
        $message .= "• お互いを尊重し、建設的なコミュニケーションを心がけましょう\n\n";
        $message .= "本日もよろしくお願いします！";

        $this->postMessage($channelId, $message);
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

    /**
     * エラー通知（管理者向け）
     */
    public function notifyError(string $error, array $context = []): void
    {
        Log::error('Slack integration error', [
            'error' => $error,
            'context' => $context,
        ]);

        // 監査ログに記録
        AuditLog::log([
            'event_type' => 'slack_error',
            'action' => 'error',
            'description' => $error,
            'metadata' => $context,
        ]);
    }
}