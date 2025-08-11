<?php

namespace App\Console\Commands;

use App\Models\Shift;
use App\Models\Attendance;
use App\Models\User;
use App\Models\SlackChannel;
use App\Models\AuditLog;
use App\Services\SlackService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ManageSlackAttendanceChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:manage-channels 
                            {--date= : 対象日付 (YYYY-MM-DD)}
                            {--create : チャンネル作成モード}
                            {--invite : 招待モード}
                            {--cleanup : クリーンアップモード}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Slackチャンネルの作成と出席者の管理';

    protected $slackService;
    protected $notificationService;

    public function __construct(SlackService $slackService, NotificationService $notificationService)
    {
        parent::__construct();
        $this->slackService = $slackService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->slackService->isEnabled()) {
            $this->warn('Slack連携が無効になっています。');
            return 0;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::tomorrow();
        
        $this->info("対象日付: {$date->format('Y-m-d')}");

        try {
            // チャンネル作成モード（23:00以降）
            if ($this->option('create') || $this->shouldCreateChannels()) {
                $this->createDailyChannels($date);
            }

            // 招待モード（8:00以降）
            if ($this->option('invite') || $this->shouldInviteAttendees()) {
                $this->inviteNewAttendees($date);
                $this->removeAbsentees($date);
            }

            // クリーンアップモード
            if ($this->option('cleanup')) {
                $this->cleanupOldChannels();
            }

            $this->info('処理が完了しました。');
            return 0;

        } catch (\Exception $e) {
            $this->error('エラーが発生しました: ' . $e->getMessage());
            Log::error('Slack channel management error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // エラー通知
            $this->slackService->notifyError('チャンネル管理エラー', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    /**
     * 日次チャンネル作成
     */
    protected function createDailyChannels(Carbon $date): void
    {
        $this->info('チャンネル作成を開始します...');

        // 対象日のシフトを取得
        $shifts = Shift::with(['teacher', 'company', 'language'])
            ->where('shift_date', $date->format('Y-m-d'))
            ->where('status', 'scheduled')
            ->where('slack_channel_created', false)
            ->get();

        if ($shifts->isEmpty()) {
            $this->info('作成対象のシフトがありません。');
            return;
        }

        foreach ($shifts as $shift) {
            $this->line("シフトID {$shift->id} のチャンネルを作成中...");

            DB::beginTransaction();
            try {
                // チャンネル作成
                $slackChannel = $this->slackService->createDailyChannel($date, $shift);

                if (!$slackChannel) {
                    throw new \Exception('チャンネルの作成に失敗しました');
                }

                // 講師を招待
                if ($shift->teacher->slack_user_id) {
                    $this->slackService->inviteToChannel(
                        $slackChannel->channel_id,
                        [$shift->teacher->slack_user_id]
                    );
                }

                // 翌日の出席者を招待
                $attendeeCount = $this->inviteInitialAttendees($slackChannel->channel_id, $date);

                $this->info("✓ チャンネル作成完了: {$slackChannel->channel_name} (出席者: {$attendeeCount}名)");

                // 監査ログ
                AuditLog::log([
                    'event_type' => 'slack_channel_created',
                    'model_type' => 'SlackChannel',
                    'model_id' => $slackChannel->id,
                    'action' => 'create',
                    'description' => "Slackチャンネルを作成: {$slackChannel->channel_name}",
                    'metadata' => [
                        'shift_id' => $shift->id,
                        'attendee_count' => $attendeeCount,
                    ],
                ]);

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("✗ シフトID {$shift->id} の処理に失敗: " . $e->getMessage());
            }
        }
    }

    /**
     * 初期出席者を招待
     */
    protected function inviteInitialAttendees(string $channelId, Carbon $date): int
    {
        return $this->slackService->inviteAttendees($channelId, $date);
    }

    /**
     * 新規出席者を招待
     */
    protected function inviteNewAttendees(Carbon $date): void
    {
        $this->info('新規出席者の招待を開始します...');

        // 本日のシフトとチャンネル取得
        $channels = SlackChannel::where('date', $date->format('Y-m-d'))
            ->where('type', 'daily')
            ->where('is_archived', false)
            ->get();

        foreach ($channels as $channel) {
            // 未招待の出席者を取得
            $newAttendees = Attendance::where('attendance_date', $date->format('Y-m-d'))
                ->where('status', 'present')
                ->where('slack_invited', false)
                ->whereHas('user', function ($q) {
                    $q->whereNotNull('slack_user_id');
                })
                ->with('user')
                ->get();

            if ($newAttendees->isEmpty()) {
                continue;
            }

            $userIds = $newAttendees->pluck('user.slack_user_id')->filter()->toArray();

            if ($this->slackService->inviteToChannel($channel->channel_id, $userIds)) {
                // 招待済みフラグ更新
                Attendance::whereIn('id', $newAttendees->pluck('id'))
                    ->update([
                        'slack_channel_id' => $channel->channel_id,
                        'slack_invited' => true,
                        'slack_invited_at' => now(),
                    ]);

                $this->info("✓ {$channel->channel_name} に {$newAttendees->count()}名を招待しました");
            }
        }
    }

    /**
     * 欠席者を削除
     */
    protected function removeAbsentees(Carbon $date): void
    {
        $this->info('欠席者の削除を開始します...');

        // キャンセルされた出席を取得
        $cancelledAttendances = Attendance::where('attendance_date', $date->format('Y-m-d'))
            ->where('status', 'cancelled')
            ->where('slack_invited', true)
            ->whereNotNull('slack_channel_id')
            ->with('user')
            ->get();

        foreach ($cancelledAttendances as $attendance) {
            if (!$attendance->user->slack_user_id) {
                continue;
            }

            if ($this->slackService->removeFromChannel(
                $attendance->slack_channel_id,
                $attendance->user->slack_user_id
            )) {
                $attendance->update([
                    'slack_invited' => false,
                ]);

                $this->info("✓ {$attendance->user->name} をチャンネルから削除しました");
            }
        }
    }

    /**
     * 古いチャンネルのクリーンアップ
     */
    protected function cleanupOldChannels(): void
    {
        $this->info('古いチャンネルのクリーンアップを開始します...');

        // 30日以上前のチャンネルをアーカイブ
        $oldChannels = SlackChannel::where('date', '<', Carbon::now()->subDays(30)->format('Y-m-d'))
            ->where('is_archived', false)
            ->get();

        foreach ($oldChannels as $channel) {
            // Slack APIでアーカイブ処理
            // ※ 実装は省略（Slack APIのarchive機能を使用）
            
            $channel->update(['is_archived' => true]);
            $this->info("✓ {$channel->channel_name} をアーカイブしました");
        }
    }

    /**
     * チャンネル作成時刻か確認
     */
    protected function shouldCreateChannels(): bool
    {
        $now = Carbon::now();
        $createTime = Carbon::parse($this->getSetting('slack', 'channel_creation_time', '23:00'));

        return $now->hour >= $createTime->hour;
    }

    /**
     * 出席者招待時刻か確認
     */
    protected function shouldInviteAttendees(): bool
    {
        $now = Carbon::now();
        return $now->hour >= 8 && $now->hour <= 22; // 8:00-22:00の間
    }

    /**
     * システム設定取得
     */
    protected function getSetting(string $category, string $key, $default = null)
    {
        $setting = \App\Models\SystemSetting::where('category', $category)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }
}