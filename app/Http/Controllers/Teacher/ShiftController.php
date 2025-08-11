<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Company;
use App\Models\Language;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShiftController extends Controller
{
    /**
     * シフト一覧表示
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);

        $query = Shift::with(['teacher', 'company', 'language'])
            ->whereYear('shift_date', $date->year)
            ->whereMonth('shift_date', $date->month);

        // 権限によるフィルタリング
        if ($user->role === 'teacher') {
            $query->where('teacher_id', $user->id);
        } elseif ($user->role === 'company_admin') {
            // 自社の講師のシフトを表示
            $teacherIds = User::where('company_id', $user->company_id)
                ->whereIn('role', ['teacher', 'company_admin'])
                ->pluck('id');
            $query->whereIn('teacher_id', $teacherIds);
        }

        $shifts = $query->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();

        // カレンダー用データ生成
        $calendar = $this->generateCalendarData($date, $shifts);

        // 講師リスト（企業管理者のみ）
        $teachers = [];
        if ($user->role === 'company_admin') {
            $teachers = User::where('company_id', $user->company_id)
                ->whereIn('role', ['teacher', 'company_admin'])
                ->orderBy('name')
                ->get();
        }

        return view('teacher.shifts.index', compact('shifts', 'calendar', 'month', 'teachers'));
    }

    /**
     * カレンダー表示
     */
    public function calendar(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        
        return view('teacher.shifts.calendar', compact('month'));
    }

    /**
     * シフト登録
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // 権限確認
        if (!in_array($user->role, ['company_admin', 'system_admin'])) {
            return back()->with('error', 'シフトの登録権限がありません。');
        }

        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'language_id' => 'required|exists:languages,id',
            'shift_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'max_students' => 'nullable|integer|min:1|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // 重複チェック
        $exists = Shift::where('teacher_id', $request->teacher_id)
            ->where('shift_date', $request->shift_date)
            ->exists();

        if ($exists) {
            return back()->with('error', 'この日付には既にシフトが登録されています。');
        }

        DB::beginTransaction();
        try {
            $shift = Shift::create([
                'teacher_id' => $request->teacher_id,
                'company_id' => $request->company_id,
                'language_id' => $request->language_id,
                'shift_date' => $request->shift_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => 'scheduled',
                'max_students' => $request->max_students ?? 20,
                'notes' => $request->notes,
            ]);

            // 監査ログ
            AuditLog::log([
                'event_type' => 'shift_created',
                'model_type' => 'Shift',
                'model_id' => $shift->id,
                'action' => 'create',
                'description' => "シフトを登録: {$request->shift_date}",
            ]);

            DB::commit();
            return back()->with('success', 'シフトを登録しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'シフトの登録に失敗しました。');
        }
    }

    /**
     * シフト更新
     */
    public function update(Request $request, Shift $shift)
    {
        $user = Auth::user();

        // 権限確認
        if ($user->role === 'teacher' && $shift->teacher_id !== $user->id) {
            return back()->with('error', '他の講師のシフトは編集できません。');
        }

        $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'max_students' => 'nullable|integer|min:1|max:100',
            'notes' => 'nullable|string|max:500',
            'status' => 'required|in:scheduled,confirmed,cancelled,completed',
        ]);

        $oldValues = $shift->toArray();

        $shift->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'max_students' => $request->max_students ?? 20,
            'notes' => $request->notes,
            'status' => $request->status,
        ]);

        // 監査ログ
        AuditLog::log([
            'event_type' => 'shift_updated',
            'model_type' => 'Shift',
            'model_id' => $shift->id,
            'action' => 'update',
            'description' => "シフトを更新: {$shift->shift_date}",
            'old_values' => $oldValues,
            'new_values' => $shift->toArray(),
        ]);

        return back()->with('success', 'シフトを更新しました。');
    }

    /**
     * シフト削除
     */
    public function destroy(Shift $shift)
    {
        $user = Auth::user();

        // 権限確認
        if (!in_array($user->role, ['company_admin', 'system_admin'])) {
            return back()->with('error', 'シフトの削除権限がありません。');
        }

        // 過去のシフトは削除不可
        if ($shift->shift_date < Carbon::today()) {
            return back()->with('error', '過去のシフトは削除できません。');
        }

        // Slackチャンネルが作成済みの場合は警告
        if ($shift->slack_channel_created) {
            return back()->with('error', 'Slackチャンネルが作成済みのシフトは削除できません。');
        }

        DB::beginTransaction();
        try {
            // 監査ログ
            AuditLog::log([
                'event_type' => 'shift_deleted',
                'model_type' => 'Shift',
                'model_id' => $shift->id,
                'action' => 'delete',
                'description' => "シフトを削除: {$shift->shift_date}",
                'old_values' => $shift->toArray(),
            ]);

            $shift->delete();

            DB::commit();
            return back()->with('success', 'シフトを削除しました。');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'シフトの削除に失敗しました。');
        }
    }

    /**
     * カレンダーデータ生成
     */
    private function generateCalendarData(Carbon $date, $shifts)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $startOfCalendar = $startOfMonth->copy()->startOfWeek();
        $endOfCalendar = $endOfMonth->copy()->endOfWeek();

        $shiftsByDate = $shifts->groupBy(function ($item) {
            return Carbon::parse($item->shift_date)->format('Y-m-d');
        });

        $calendar = [];
        $current = $startOfCalendar->copy();

        while ($current <= $endOfCalendar) {
            $dateKey = $current->format('Y-m-d');
            $dayShifts = $shiftsByDate->get($dateKey, collect());

            $calendar[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'isCurrentMonth' => $current->month === $date->month,
                'isWeekend' => $current->isWeekend(),
                'isToday' => $current->isToday(),
                'isFuture' => $current->isFuture(),
                'shifts' => $dayShifts,
            ];

            $current->addDay();
        }

        return collect($calendar)->chunk(7);
    }
}