<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 出席履歴一覧
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // フィルター用の年月
        $yearMonth = $request->get('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $yearMonth);

        // 月間の出席データ取得
        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('attendance_date', $date->year)
            ->whereMonth('attendance_date', $date->month)
            ->orderBy('attendance_date', 'desc')
            ->get();

        // 統計データ
        $stats = [
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'cancelled' => $attendances->where('status', 'cancelled')->count(),
            'late' => $attendances->where('status', 'late')->count(),
        ];

        // 出席率計算
        $totalDays = $stats['present'] + $stats['absent'] + $stats['late'];
        $attendanceRate = $totalDays > 0 
            ? round((($stats['present'] + $stats['late']) / $totalDays) * 100, 1) 
            : 0;

        // カレンダー用データ生成
        $calendar = $this->generateCalendarData($date, $attendances);

        // 今日と明日の出席情報
        $todayAttendance = $this->getTodayAttendance($user);
        $tomorrowAttendance = $this->getTomorrowAttendance($user);

        return view('student.attendance.index', compact(
            'attendances',
            'stats',
            'attendanceRate',
            'calendar',
            'yearMonth',
            'todayAttendance',
            'tomorrowAttendance'
        ));
    }

    /**
     * 出席登録
     */
    public function register(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|before_or_equal:tomorrow',
        ]);

        $user = Auth::user();
        $date = Carbon::parse($request->date);

        // 登録可能時間チェック
        if (!$this->canRegisterAttendance($date)) {
            return back()->with('error', '出席登録可能時間外です。');
        }

        // 既存の出席記録確認
        $attendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $date->format('Y-m-d'))
            ->first();

        if ($attendance) {
            if ($attendance->status === 'present') {
                return back()->with('info', '既に出席登録されています。');
            }

            // キャンセル済みの場合は更新
            $attendance->update([
                'status' => 'present',
                'check_in_time' => $date->isToday() ? now()->format('H:i:s') : null,
            ]);
        } else {
            // 新規登録
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'attendance_date' => $date->format('Y-m-d'),
                'status' => 'present',
                'check_in_time' => $date->isToday() ? now()->format('H:i:s') : null,
                'ip_address' => $request->ip(),
            ]);
        }

        // 監査ログ
        AuditLog::log([
            'event_type' => 'attendance_registered',
            'model_type' => 'Attendance',
            'model_id' => $attendance->id,
            'action' => 'register',
            'description' => "出席登録: {$date->format('Y-m-d')}",
        ]);

        return back()->with('success', '出席登録が完了しました。');
    }

    /**
     * 出席キャンセル
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|before_or_equal:tomorrow',
        ]);

        $user = Auth::user();
        $date = Carbon::parse($request->date);

        // キャンセル可能時間チェック
        if (!$this->canCancelAttendance($date)) {
            return back()->with('error', 'キャンセル可能時間外です。');
        }

        $attendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $date->format('Y-m-d'))
            ->first();

        if (!$attendance) {
            return back()->with('error', '出席登録がありません。');
        }

        if ($attendance->status === 'cancelled') {
            return back()->with('info', '既にキャンセルされています。');
        }

        // キャンセル処理
        $attendance->update([
            'status' => 'cancelled',
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'attendance_cancelled',
            'model_type' => 'Attendance',
            'model_id' => $attendance->id,
            'action' => 'cancel',
            'description' => "出席キャンセル: {$date->format('Y-m-d')}",
        ]);

        return back()->with('success', '出席をキャンセルしました。');
    }

    /**
     * カレンダーデータ生成
     */
    private function generateCalendarData(Carbon $date, $attendances)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $startOfCalendar = $startOfMonth->copy()->startOfWeek();
        $endOfCalendar = $endOfMonth->copy()->endOfWeek();

        $attendanceByDate = $attendances->keyBy(function ($item) {
            return Carbon::parse($item->attendance_date)->format('Y-m-d');
        });

        $calendar = [];
        $current = $startOfCalendar->copy();

        while ($current <= $endOfCalendar) {
            $dateKey = $current->format('Y-m-d');
            $attendance = $attendanceByDate->get($dateKey);

            $calendar[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'isCurrentMonth' => $current->month === $date->month,
                'isWeekend' => $current->isWeekend(),
                'isToday' => $current->isToday(),
                'isFuture' => $current->isFuture(),
                'canRegister' => $this->canRegisterAttendance($current),
                'status' => $attendance ? $attendance->status : null,
            ];

            $current->addDay();
        }

        return collect($calendar)->chunk(7);
    }

    /**
     * 出席登録可能か確認
     */
    private function canRegisterAttendance(Carbon $date): bool
    {
        $now = Carbon::now();

        // 今日の場合
        if ($date->isToday()) {
            // 7:55以降のみ登録可能
            return $now->hour >= 7 && $now->minute >= 55;
        }

        // 明日の場合
        if ($date->isTomorrow()) {
            return true;
        }

        return false;
    }

    /**
     * キャンセル可能か確認
     */
    private function canCancelAttendance(Carbon $date): bool
    {
        // 今日と明日のみキャンセル可能
        return $date->isToday() || $date->isTomorrow();
    }

    /**
     * 今日の出席情報取得
     */
    private function getTodayAttendance($user)
    {
        return Attendance::where('user_id', $user->id)
            ->where('attendance_date', Carbon::today())
            ->first();
    }

    /**
     * 明日の出席情報取得
     */
    private function getTomorrowAttendance($user)
    {
        return Attendance::where('user_id', $user->id)
            ->where('attendance_date', Carbon::tomorrow())
            ->first();
    }
}