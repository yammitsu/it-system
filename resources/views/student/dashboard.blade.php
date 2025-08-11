@extends('layouts.app')

@section('title', 'ダッシュボード')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-home me-2"></i>ダッシュボード
        </h1>
    </div>
</div>

<!-- 進捗サマリー -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-primary" style="border-left-color: #4e73df;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">全課題数</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $progressStats['total'] }}</div>
                </div>
                <div class="text-primary">
                    <i class="fas fa-tasks fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-success" style="border-left-color: #1cc88a;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">完了済み</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $progressStats['completed'] }}</div>
                </div>
                <div class="text-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-warning" style="border-left-color: #f6c23e;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">進行中</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $progressStats['in_progress'] }}</div>
                </div>
                <div class="text-warning">
                    <i class="fas fa-spinner fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-info" style="border-left-color: #36b9cc;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">完了率</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $progressStats['completion_rate'] }}%</div>
                </div>
                <div class="text-info">
                    <i class="fas fa-percentage fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 左カラム -->
    <div class="col-lg-8">
        <!-- 出席カレンダー -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>今月の出席状況
                </h5>
            </div>
            <div class="card-body">
                <!-- 出席ボタン -->
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            @if($todayAttendance && $todayAttendance->status === 'present')
                                <button class="btn btn-success w-100" disabled>
                                    <i class="fas fa-check me-2"></i>本日出席済み
                                </button>
                            @elseif($todayAttendance && $todayAttendance->status === 'cancelled')
                                <form method="POST" action="{{ route('student.attendance.register') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-user-check me-2"></i>本日出席する
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('student.attendance.register') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-user-check me-2"></i>本日出席する
                                    </button>
                                </form>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($tomorrowAttendance && $tomorrowAttendance->status === 'present')
                                <form method="POST" action="{{ route('student.attendance.cancel') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-times me-2"></i>明日の出席をキャンセル
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('student.attendance.register') }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-calendar-plus me-2"></i>明日出席予約
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- カレンダー表示 -->
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
                            @php
                                $weeks = collect($attendanceCalendar)->chunk(7);
                            @endphp
                            @foreach($weeks as $week)
                            <tr>
                                @foreach($week as $day)
                                <td class="{{ $day['isToday'] ? 'bg-warning bg-opacity-25' : '' }}">
                                    <div>{{ $day['day'] }}</div>
                                    @if($day['status'] === 'present')
                                        <span class="badge bg-success">出席</span>
                                    @elseif($day['status'] === 'absent')
                                        <span class="badge bg-danger">欠席</span>
                                    @elseif($day['status'] === 'cancelled')
                                        <span class="badge bg-secondary">キャンセル</span>
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
        
        <!-- 最近の課題 -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>最近の課題
                    </h5>
                    <a href="{{ route('student.tasks.index') }}" class="btn btn-sm btn-outline-primary">
                        すべて見る <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="list-group">
                    @forelse($recentTasks as $task)
                    <a href="{{ route('student.tasks.show', $task) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">{{ $task->title }}</h6>
                                <small class="text-muted">
                                    {{ $task->category_major }} / {{ $task->category_minor }}
                                </small>
                            </div>
                            <div>
                                @if($task->submissions->first())
                                    @if($task->submissions->first()->status === 'completed')
                                        <span class="badge bg-success">完了</span>
                                    @elseif($task->submissions->first()->status === 'in_progress')
                                        <span class="badge bg-warning">進行中</span>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">未着手</span>
                                @endif
                            </div>
                        </div>
                    </a>
                    @empty
                    <p class="text-muted text-center my-3">課題がありません</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    
    <!-- 右カラム -->
    <div class="col-lg-4">
        <!-- 学習時間 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>学習時間
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>今週</span>
                        <strong>{{ $studyStats['weekly'] }}時間</strong>
                    </div>
                    <div class="progress mt-2" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ min(($studyStats['weekly'] / 40) * 100, 100) }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>今月</span>
                        <strong>{{ $studyStats['monthly'] }}時間</strong>
                    </div>
                    <div class="progress mt-2" style="height: 10px;">
                        <div class="progress-bar bg-info" style="width: {{ min(($studyStats['monthly'] / 160) * 100, 100) }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>累計</span>
                        <strong>{{ $studyStats['total'] }}時間</strong>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <h4 class="mb-0">{{ $studyStats['consecutive_days'] }}</h4>
                    <small class="text-muted">連続学習日数</small>
                </div>
            </div>
        </div>
        
        <!-- 通知 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bell me-2"></i>通知
                </h5>
            </div>
            <div class="card-body">
                @forelse($unreadNotifications as $notification)
                <div class="d-flex mb-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1">{{ $notification->title }}</h6>
                        <p class="mb-1 small">{{ Str::limit($notification->message, 50) }}</p>
                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center">新しい通知はありません</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection