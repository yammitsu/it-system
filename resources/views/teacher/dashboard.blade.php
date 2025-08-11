@extends('layouts.app')

@section('title', 'ダッシュボード')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-home me-2"></i>講師ダッシュボード
            @if(isset($isCompanyAdmin) && $isCompanyAdmin)
                <span class="badge bg-info ms-2">企業管理者</span>
            @endif
        </h1>
    </div>
</div>

<!-- 企業管理者用統計（企業管理者のみ表示） -->
@if(isset($isCompanyAdmin) && $isCompanyAdmin)
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-primary" style="border-left-color: #4e73df;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">総ユーザー数</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $companyStats['total_users'] }}</div>
                </div>
                <div class="text-primary">
                    <i class="fas fa-users fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-success" style="border-left-color: #1cc88a;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">アクティブ受講生</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $companyStats['active_students'] }}</div>
                </div>
                <div class="text-success">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-warning" style="border-left-color: #f6c23e;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">完了率</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $companyStats['completion_rate'] }}%</div>
                </div>
                <div class="text-warning">
                    <i class="fas fa-chart-line fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card border-start-info" style="border-left-color: #36b9cc;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-xs text-muted text-uppercase mb-1">出席率</div>
                    <div class="h5 mb-0 font-weight-bold">{{ $attendanceRate }}%</div>
                </div>
                <div class="text-info">
                    <i class="fas fa-calendar-check fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <!-- 左カラム -->
    <div class="col-lg-8">
        <!-- 本日のシフト -->
        @if($todayShift)
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>本日のシフト
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>企業:</strong> {{ $todayShift->company->name }}</p>
                        <p><strong>言語:</strong> {{ $todayShift->language->name }}</p>
                        <p><strong>時間:</strong> {{ $todayShift->start_time }} - {{ $todayShift->end_time }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>最大人数:</strong> {{ $todayShift->max_students }}名</p>
                        <p><strong>現在の受講生:</strong> {{ $todayShift->current_students }}名</p>
                        @if($todayShift->slack_channel_id)
                        <p><strong>Slackチャンネル:</strong> #{{ $todayShift->slack_channel_id }}</p>
                        @endif
                    </div>
                </div>
                
                @if($todayAttendees->count() > 0)
                <hr>
                <h6>本日の出席者（{{ $todayAttendees->count() }}名）</h6>
                <div class="row">
                    @foreach($todayAttendees as $attendee)
                    <div class="col-md-6 mb-2">
                        <small>
                            <i class="fas fa-user me-1"></i>
                            {{ $attendee->user->name }}
                            @if($attendee->check_in_time)
                                <span class="text-muted">({{ $attendee->check_in_time }})</span>
                            @endif
                        </small>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @else
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>本日のシフトはありません
        </div>
        @endif
        
        <!-- 評価待ち課題 -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>評価待ち課題
                        <span class="badge bg-danger ms-2">{{ $recentSubmissions->count() }}</span>
                    </h5>
                    <a href="{{ route('teacher.tasks.index') }}" class="btn btn-sm btn-outline-primary">
                        すべて見る <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($recentSubmissions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>受講生</th>
                                <th>課題</th>
                                <th>完了日</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentSubmissions as $submission)
                            <tr>
                                <td>{{ $submission->user->name }}</td>
                                <td>{{ Str::limit($submission->task->title, 30) }}</td>
                                <td>{{ $submission->completed_at->format('m/d') }}</td>
                                <td>
                                    <a href="{{ route('teacher.tasks.submissions', $submission->task) }}" 
                                       class="btn btn-sm btn-outline-success">
                                        評価
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted text-center my-3">評価待ちの課題はありません</p>
                @endif
            </div>
        </div>
        
        <!-- 今週のシフト -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-week me-2"></i>今週のシフト
                </h5>
            </div>
            <div class="card-body">
                @if($weekShifts->count() > 0)
                <div class="list-group">
                    @foreach($weekShifts as $shift)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    {{ $shift->shift_date->format('m/d') }}
                                    ({{ $shift->shift_date->locale('ja')->dayName }})
                                </h6>
                                <small class="text-muted">
                                    {{ $shift->company->name }} - {{ $shift->language->name }}
                                    ({{ $shift->start_time }} - {{ $shift->end_time }})
                                </small>
                            </div>
                            <div>
                                @switch($shift->status)
                                    @case('scheduled')
                                        <span class="badge bg-primary">予定</span>
                                        @break
                                    @case('confirmed')
                                        <span class="badge bg-success">確定</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-danger">キャンセル</span>
                                        @break
                                    @case('completed')
                                        <span class="badge bg-secondary">完了</span>
                                        @break
                                @endswitch
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center my-3">今週のシフトはありません</p>
                @endif
            </div>
        </div>
    </div>
    
    <!-- 右カラム -->
    <div class="col-lg-4">
        <!-- 受講生統計 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>受講生統計
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>総受講生数</span>
                        <strong>{{ $studentStats['total'] }}名</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>アクティブ</span>
                        <strong>{{ $studentStats['active'] }}名</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>平均進捗率</span>
                        <strong>{{ $studentStats['average_progress'] }}%</strong>
                    </div>
                    <div class="progress mt-2" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ $studentStats['average_progress'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 課題統計 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>課題統計
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>総課題数</span>
                        <strong>{{ $taskStats['total'] }}個</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>アクティブ</span>
                        <strong>{{ $taskStats['active'] }}個</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>評価待ち</span>
                        <strong class="text-danger">{{ $taskStats['pending_evaluations'] }}件</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- クイックアクション -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>クイックアクション
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('teacher.tasks.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>新規課題作成
                    </a>
                    <a href="{{ route('teacher.students.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-users me-2"></i>受講生一覧
                    </a>
                    <a href="{{ route('teacher.shifts.calendar') }}" class="btn btn-outline-primary">
                        <i class="fas fa-calendar me-2"></i>シフトカレンダー
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection