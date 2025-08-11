@extends('layouts.app')

@section('title', '出席管理')

@section('breadcrumb')
<li class="breadcrumb-item active">出席管理</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-calendar-check me-2"></i>出席管理
        </h1>
    </div>
</div>

<!-- 出席ボタン -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">本日の出席</h5>
                @if($todayAttendance && $todayAttendance->status === 'present')
                    <div class="alert alert-success mb-3">
                        <i class="fas fa-check-circle me-2"></i>本日は出席済みです
                        <small class="d-block mt-1">チェックイン: {{ $todayAttendance->check_in_time }}</small>
                    </div>
                @elseif($todayAttendance && $todayAttendance->status === 'cancelled')
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-times-circle me-2"></i>本日の出席はキャンセルされています
                    </div>
                    <form method="POST" action="{{ route('student.attendance.register') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-check me-2"></i>出席する
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('student.attendance.register') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-check me-2"></i>出席する
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">明日の出席予約</h5>
                @if($tomorrowAttendance && $tomorrowAttendance->status === 'present')
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-calendar-check me-2"></i>明日の出席は予約済みです
                    </div>
                    <form method="POST" action="{{ route('student.attendance.cancel') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-times me-2"></i>予約をキャンセル
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('student.attendance.register') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-calendar-plus me-2"></i>出席を予約
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 統計情報 -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">出席</div>
                <div class="h5 mb-0 font-weight-bold">{{ $stats['present'] }}日</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-danger">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">欠席</div>
                <div class="h5 mb-0 font-weight-bold">{{ $stats['absent'] }}日</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">遅刻</div>
                <div class="h5 mb-0 font-weight-bold">{{ $stats['late'] }}日</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">出席率</div>
                <div class="h5 mb-0 font-weight-bold">{{ $attendanceRate }}%</div>
            </div>
        </div>
    </div>
</div>

<!-- カレンダー -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-calendar-alt me-2"></i>出席カレンダー
            </h5>
            <form method="GET" action="{{ route('student.attendance.index') }}" class="d-flex">
                <input type="month" name="month" value="{{ $yearMonth }}" class="form-control me-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered text-center">
                <thead>
                    <tr>
                        <th class="text-danger">日</th>
                        <th>月</th>
                        <th>火</th>
                        <th>水</th>
                        <th>木</th>
                        <th>金</th>
                        <th class="text-primary">土</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($calendar as $week)
                    <tr>
                        @foreach($week as $day)
                        <td class="{{ $day['isToday'] ? 'bg-warning bg-opacity-10' : '' }} {{ !$day['isCurrentMonth'] ? 'text-muted' : '' }}">
                            <div class="mb-1">{{ $day['day'] }}</div>
                            @if($day['isCurrentMonth'] && !$day['isWeekend'])
                                @if($day['status'] === 'present')
                                    <span class="badge bg-success">出席</span>
                                @elseif($day['status'] === 'absent')
                                    <span class="badge bg-danger">欠席</span>
                                @elseif($day['status'] === 'late')
                                    <span class="badge bg-warning">遅刻</span>
                                @elseif($day['status'] === 'cancelled')
                                    <span class="badge bg-secondary">キャンセル</span>
                                @elseif($day['canRegister'])
                                    <button class="btn btn-sm btn-outline-primary attendance-btn" 
                                            data-date="{{ $day['date'] }}"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#attendanceModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                @endif
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 出席履歴 -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>出席履歴
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>曜日</th>
                        <th>ステータス</th>
                        <th>チェックイン</th>
                        <th>チェックアウト</th>
                        <th>学習時間</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $attendance)
                    <tr>
                        <td>{{ $attendance->attendance_date->format('Y/m/d') }}</td>
                        <td>{{ $attendance->attendance_date->locale('ja')->dayName }}</td>
                        <td>
                            @switch($attendance->status)
                                @case('present')
                                    <span class="badge bg-success">出席</span>
                                    @break
                                @case('absent')
                                    <span class="badge bg-danger">欠席</span>
                                    @break
                                @case('late')
                                    <span class="badge bg-warning">遅刻</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge bg-secondary">キャンセル</span>
                                    @break
                            @endswitch
                        </td>
                        <td>{{ $attendance->check_in_time ?? '-' }}</td>
                        <td>{{ $attendance->check_out_time ?? '-' }}</td>
                        <td>
                            @if($attendance->study_minutes > 0)
                                {{ floor($attendance->study_minutes / 60) }}時間{{ $attendance->study_minutes % 60 }}分
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">出席履歴がありません</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 出席登録モーダル -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">出席登録</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('student.attendance.register') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="date" id="modalDate">
                    <p>選択した日付: <span id="modalDateDisplay"></span></p>
                    <p>この日の出席を登録しますか？</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">登録</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 出席ボタンクリック時の処理
    document.querySelectorAll('.attendance-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const date = this.dataset.date;
            document.getElementById('modalDate').value = date;
            document.getElementById('modalDateDisplay').textContent = date;
        });
    });
</script>
@endpush