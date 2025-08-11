<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use App\Services\NotificationService;
use App\Models\Company;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendDailyReports extends Command
{
    protected $signature = 'reports:send-daily';
    protected $description = '日次レポートの送信';
    
    protected $reportService;
    protected $notificationService;

    public function __construct(ReportService $reportService, NotificationService $notificationService)
    {
        parent::__construct();
        $this->reportService = $reportService;
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('日次レポート送信を開始します...');

        // 週次レポート（月曜日）
        if (Carbon::now()->isMonday()) {
            $this->sendWeeklyReports();
        }

        // 月次レポート（月初）
        if (Carbon::now()->day === 1) {
            $this->sendMonthlyReports();
        }

        // 日次通知
        $this->sendDailyNotifications();

        $this->info('日次レポート送信が完了しました。');
        return 0;
    }

    /**
     * 週次レポート送信
     */
    private function sendWeeklyReports()
    {
        if (!$this->isEnabled('report', 'weekly_report_enabled')) {
            return;
        }

        $this->info('週次レポートを送信中...');

        $companies = Company::active()->get();

        foreach ($companies as $company) {
            try {
                // レポート生成
                $report = $this->reportService->generateWeeklyReport(
                    $company,
                    Carbon::now()->subWeek()->startOfWeek()
                );

                // 管理者に送信
                $admins = User::where('company_id', $company->id)
                    ->whereIn('role', ['company_admin', 'teacher'])
                    ->get();

                foreach ($admins as $admin) {
                    $this->notificationService->send(
                        $admin,
                        'report',
                        '週次レポートが生成されました',
                        "先週の学習状況レポートが利用可能です。\nダウンロードURL: {$report['url']}",
                        ['report' => $report]
                    );
                }

                $this->info("✓ {$company->name} の週次レポートを送信しました");

            } catch (\Exception $e) {
                $this->error("✗ {$company->name} の週次レポート送信に失敗: " . $e->getMessage());
            }
        }
    }

    /**
     * 月次レポート送信
     */
    private function sendMonthlyReports()
    {
        if (!$this->isEnabled('report', 'monthly_report_enabled')) {
            return;
        }

        $this->info('月次レポートを送信中...');

        $companies = Company::active()->get();

        foreach ($companies as $company) {
            try {
                // レポート生成
                $report = $this->reportService->generateMonthlyReport(
                    $company,
                    Carbon::now()->subMonth()->startOfMonth()
                );

                // 管理者に送信
                $admins = User::where('company_id', $company->id)
                    ->whereIn('role', ['company_admin'])
                    ->get();

                foreach ($admins as $admin) {
                    $this->notificationService->send(
                        $admin,
                        'report',
                        '月次レポートが生成されました',
                        "先月の詳細レポートが利用可能です。\nダウンロードURL: {$report['url']}",
                        ['report' => $report]
                    );
                }

                $this->info("✓ {$company->name} の月次レポートを送信しました");

            } catch (\Exception $e) {
                $this->error("✗ {$company->name} の月次レポート送信に失敗: " . $e->getMessage());
            }
        }
    }

    /**
     * 日次通知送信
     */
    private function sendDailyNotifications()
    {
        $this->info('日次通知を送信中...');

        // 出席リマインダー
        $this->sendAttendanceReminders();

        // 課題期限通知
        $this->sendTaskDeadlineNotifications();

        // 長期未ログイン通知
        $this->sendInactivityNotifications();
    }

    /**
     * 出席リマインダー
     */
    private function sendAttendanceReminders()
    {
        // 明日の出席未登録者
        $tomorrow = Carbon::tomorrow();
        
        $studentsWithoutAttendance = User::where('role', 'student')
            ->where('status', 'active')
            ->whereDoesntHave('attendances', function ($q) use ($tomorrow) {
                $q->where('attendance_date', $tomorrow->format('Y-m-d'))
                    ->where('status', 'present');
            })
            ->get();

        foreach ($studentsWithoutAttendance as $student) {
            $this->notificationService->send(
                $student,
                'attendance_reminder',
                '明日の出席登録をお忘れなく',
                '明日の講習の出席登録がまだ完了していません。忘れずに登録してください。',
                ['date' => $tomorrow->format('Y-m-d')]
            );
        }

        $this->info("✓ {$studentsWithoutAttendance->count()}名に出席リマインダーを送信しました");
    }

    /**
     * 課題期限通知
     */
    private function sendTaskDeadlineNotifications()
    {
        // 実装予定の課題期限機能に基づく通知
        // 現在は課題に期限がないため、長期未完了課題の通知を送信
        
        $studentsWithPendingTasks = User::where('role', 'student')
            ->where('status', 'active')
            ->whereHas('taskSubmissions', function ($q) {
                $q->where('status', 'in_progress')
                    ->where('started_at', '<', Carbon::now()->subWeeks(2));
            })
            ->get();

        foreach ($studentsWithPendingTasks as $student) {
            $pendingTasks = $student->taskSubmissions()
                ->where('status', 'in_progress')
                ->where('started_at', '<', Carbon::now()->subWeeks(2))
                ->with('task')
                ->get();

            $taskList = $pendingTasks->pluck('task.title')->implode(', ');

            $this->notificationService->send(
                $student,
                'task_deadline',
                '未完了の課題があります',
                "以下の課題が2週間以上進行中です：\n{$taskList}",
                ['tasks' => $pendingTasks->pluck('task_id')]
            );
        }

        $this->info("✓ {$studentsWithPendingTasks->count()}名に課題リマインダーを送信しました");
    }

    /**
     * 長期未ログイン通知
     */
    private function sendInactivityNotifications()
    {
        $inactiveThreshold = Carbon::now()->subWeek();
        
        $inactiveStudents = User::where('role', 'student')
            ->where('status', 'active')
            ->where(function ($q) use ($inactiveThreshold) {
                $q->whereNull('last_login_at')
                    ->orWhere('last_login_at', '<', $inactiveThreshold);
            })
            ->get();

        foreach ($inactiveStudents as $student) {
            $this->notificationService->send(
                $student,
                'inactivity',
                'しばらくログインされていません',
                '1週間以上ログインされていません。学習を継続するために、システムにアクセスしてください。',
                ['last_login' => $student->last_login_at]
            );

            // 管理者にも通知
            if ($student->company) {
                $admins = User::where('company_id', $student->company_id)
                    ->whereIn('role', ['company_admin', 'teacher'])
                    ->get();

                foreach ($admins as $admin) {
                    $this->notificationService->send(
                        $admin,
                        'student_inactive',
                        '長期未ログインの受講生',
                        "{$student->name}さんが1週間以上ログインしていません。",
                        ['student_id' => $student->id]
                    );
                }
            }
        }

        $this->info("✓ {$inactiveStudents->count()}名の未ログイン通知を送信しました");
    }

    /**
     * 設定確認
     */
    private function isEnabled(string $category, string $key): bool
    {
        $setting = SystemSetting::where('category', $category)
            ->where('key', $key)
            ->first();

        return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : false;
    }
}

// app/Console/Commands/ProcessScheduledNotifications.php
namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ProcessScheduledNotifications extends Command
{
    protected $signature = 'notifications:process-scheduled';
    protected $description = 'スケジュール済み通知の処理';
    
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('スケジュール済み通知の処理を開始します...');

        $pendingNotifications = Notification::pending()->get();

        foreach ($pendingNotifications as $notification) {
            try {
                // 通知送信処理
                $this->processNotification($notification);
                
                $this->info("✓ 通知ID {$notification->id} を送信しました");

            } catch (\Exception $e) {
                $this->error("✗ 通知ID {$notification->id} の送信に失敗: " . $e->getMessage());
            }
        }

        $this->info("処理完了: {$pendingNotifications->count()}件の通知を処理しました");
        return 0;
    }

    /**
     * 通知処理
     */
    private function processNotification(Notification $notification)
    {
        switch ($notification->channel) {
            case 'email':
                // メール送信処理
                $this->sendEmail($notification);
                break;
                
            case 'slack':
                // Slack送信処理
                $this->sendSlack($notification);
                break;
                
            case 'push':
                // プッシュ通知処理
                $this->sendPush($notification);
                break;
        }

        $notification->markAsSent();
    }

    private function sendEmail(Notification $notification)
    {
        // メール送信の実装
        // Mail::to($notification->user)->send(new NotificationMail($notification));
    }

    private function sendSlack(Notification $notification)
    {
        // Slack送信の実装
        if ($notification->user->slack_user_id) {
            app(SlackService::class)->postMessage(
                $notification->user->slack_user_id,
                "*{$notification->title}*\n{$notification->message}"
            );
        }
    }

    private function sendPush(Notification $notification)
    {
        // プッシュ通知の実装
    }
}