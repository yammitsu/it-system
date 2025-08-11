<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Attendance;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * ダッシュボード表示
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // 進捗統計
        $progressStats = $this->getProgressStats($user);

        // 今月の出席カレンダーデータ
        $attendanceCalendar = $this->getAttendanceCalendar($user);

        // 今日と明日の出席状態
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $today)
            ->first();

        $tomorrowAttendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $tomorrow)
            ->first();

        // 最近の課題
        $recentTasks = Task::with(['submissions' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->where('language_id', $user->language_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->available()
            ->ordered()
            ->limit(5)
            ->get();

        // 未読通知
        $unreadNotifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 学習時間統計
        $studyStats = $this->getStudyStats($user);

        // お知らせ
        $announcements = $this->getAnnouncements($user);

        return view('student.dashboard', compact(
            'progressStats',
            'attendanceCalendar',
            'todayAttendance',
            'tomorrowAttendance',
            'recentTasks',
            'unreadNotifications',
            'studyStats',
            'announcements'
        ));
    }

    /**
     * 進捗統計取得
     */
    private function getProgressStats($user)
    {
        $totalTasks = Task::where('language_id', $user->language_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            })
            ->available()
            ->count();

        $completedTasks = TaskSubmission::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $inProgressTasks = TaskSubmission::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'in_progress' => $inProgressTasks,
            'not_started' => $totalTasks - $completedTasks - $inProgressTasks,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * 出席カレンダーデータ取得
     */
    private function getAttendanceCalendar($user)
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->attendance_date)->format('Y-m-d');
            });

        $calendar = [];
        $current = $startOfMonth->copy();

        while ($current <= $endOfMonth) {
            $dateKey = $current->format('Y-m-d');
            $attendance = $attendances->get($dateKey);

            $calendar[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'dayOfWeek' => $current->dayOfWeek,
                'isWeekend' => $current->isWeekend(),
                'isToday' => $current->isToday(),
                'isFuture' => $current->isFuture(),
                'status' => $attendance ? $attendance->status : null,
            ];

            $current->addDay();
        }

        return $calendar;
    }

    /**
     * 学習時間統計取得
     */
    private function getStudyStats($user)
    {
        // 今週の学習時間
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $weeklyHours = Attendance::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$weekStart, $weekEnd])
            ->sum('study_minutes') / 60;

        // 今月の学習時間
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $monthlyHours = Attendance::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
            ->sum('study_minutes') / 60;

        // 総学習時間
        $totalHours = $user->total_study_hours / 60;

        return [
            'weekly' => round($weeklyHours, 1),
            'monthly' => round($monthlyHours, 1),
            'total' => round($totalHours, 1),
            'consecutive_days' => $user->consecutive_days,
        ];
    }

    /**
     * お知らせ取得
     */
    private function getAnnouncements($user)
    {
        return Notification::where('user_id', $user->id)
            ->where('type', 'announcement')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
    }
}