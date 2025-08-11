@extends('layouts.app')

@section('title', '分析ダッシュボード')

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('teacher.reports.index') }}">レポート</a></li>
<li class="breadcrumb-item active">分析ダッシュボード</li>
@endsection

@push('styles')
<style>
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .risk-item {
        border-left: 4px solid;
        padding: 15px;
        margin-bottom: 10px;
        background: white;
        border-radius: 5px;
    }
    
    .risk-high {
        border-left-color: #dc3545;
    }
    
    .risk-medium {
        border-left-color: #ffc107;
    }
    
    .risk-low {
        border-left-color: #28a745;
    }
    
    .progress-ring {
        transform: rotate(-90deg);
    }
    
    .progress-ring-circle {
        transition: stroke-dashoffset 1s;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-chart-line me-2"></i>分析ダッシュボード
            </h1>
            <div>
                <form method="GET" action="{{ route('teacher.reports.analytics') }}" class="d-inline-flex gap-2">
                    <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="form-control">
                    <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="form-control">
                    @if(auth()->user()->role === 'system_admin')
                    <select name="company_id" class="form-select">
                        @foreach(App\Models\Company::all() as $comp)
                        <option value="{{ $comp->id }}" {{ $company->id == $comp->id ? 'selected' : '' }}>
                            {{ $comp->name }}
                        </option>
                        @endforeach
                    </select>
                    @endif
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync"></i> 更新
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 概要統計 -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-box">
            <h6 class="text-white-50">総受講生数</h6>
            <h2>{{ $analytics['overview']['total_students'] }}</h2>
            <small>アクティブ: {{ $analytics['overview']['active_students'] }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box">
            <h6 class="text-white-50">平均完了率</h6>
            <h2>{{ $analytics['overview']['average_completion_rate'] }}%</h2>
            <div class="progress mt-2" style="height: 5px;">
                <div class="progress-bar bg-white" style="width: {{ $analytics['overview']['average_completion_rate'] }}%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box">
            <h6 class="text-white-50">平均スコア</h6>
            <h2>{{ $analytics['overview']['average_score'] }}</h2>
            <small>100点満点</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box">
            <h6 class="text-white-50">課題数</h6>
            <h2>{{ $analytics['overview']['total_tasks'] }}</h2>
            <small>進捗あり: {{ $analytics['overview']['students_with_progress'] }}名</small>
        </div>
    </div>
</div>

<div class="row">
    <!-- 進捗トレンド -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">進捗トレンド</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="progressTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 出席パターン -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">出席パターン分析</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>曜日別出席率</h6>
                        <div class="chart-container">
                            <canvas id="attendanceByDayChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>連続出席統計</h6>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>平均連続日数</span>
                                <strong>{{ $analytics['attendance_patterns']['consecutive_stats']['average'] }}日</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>最大連続日数</span>
                                <strong>{{ $analytics['attendance_patterns']['consecutive_stats']['maximum'] }}日</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>5日以上連続</span>
                                <strong>{{ $analytics['attendance_patterns']['consecutive_stats']['over_5_days_count'] }}名</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 課題パフォーマンス -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">課題パフォーマンス</h5>
            </div>
            <div class="card-body">
                <nav>
                    <div class="nav nav-tabs" id="taskTab" role="tablist">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#difficulty">
                            難易度別
                        </button>
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#category">
                            カテゴリ別
                        </button>
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hardest">
                            難関課題
                        </button>
                    </div>
                </nav>
                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="difficulty">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>難易度</th>
                                    <th>課題数</th>
                                    <th>平均完了率</th>
                                    <th>平均スコア</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($analytics['task_performance']['by_difficulty'] as $level => $stats)
                                <tr>
                                    <td>
                                        @switch($level)
                                            @case('beginner')
                                                <span class="badge bg-success">初級</span>
                                                @break
                                            @case('intermediate')
                                                <span class="badge bg-warning">中級</span>
                                                @break
                                            @case('advanced')
                                                <span class="badge bg-danger">上級</span>
                                                @break
                                            @case('expert')
                                                <span class="badge bg-dark">エキスパート</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>{{ $stats['count'] }}</td>
                                    <td>{{ round($stats['avg_completion_rate'], 1) }}%</td>
                                    <td>{{ round($stats['avg_score'], 1) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="category">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>カテゴリ</th>
                                    <th>課題数</th>
                                    <th>挑戦者数</th>
                                    <th>完了者数</th>
                                    <th>平均スコア</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($analytics['task_performance']['by_category'] as $category => $stats)
                                <tr>
                                    <td>{{ $category }}</td>
                                    <td>{{ $stats['count'] }}</td>
                                    <td>{{ $stats['total_attempted'] }}</td>
                                    <td>{{ $stats['total_completed'] }}</td>
                                    <td>{{ round($stats['avg_score'], 1) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="hardest">
                        <h6>最も難しい課題 TOP5</h6>
                        <ol>
                            @foreach($analytics['task_performance']['hardest_tasks'] as $task)
                            <li>
                                {{ $task->title }}
                                <small class="text-muted">
                                    (完了率: {{ $task->attempted_count > 0 ? round(($task->completed_count / $task->attempted_count) * 100, 1) : 0 }}%)
                                </small>
                            </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 右サイドバー -->
    <div class="col-lg-4">
        <!-- 完了予測 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">完了予測</h5>
            </div>
            <div class="card-body text-center">
                <svg width="150" height="150">
                    <circle class="progress-ring-circle"
                            stroke="#e0e0e0"
                            stroke-width="10"
                            fill="transparent"
                            r="65"
                            cx="75"
                            cy="75"/>
                    <circle class="progress-ring progress-ring-circle"
                            stroke="#667eea"
                            stroke-width="10"
                            fill="transparent"
                            r="65"
                            cx="75"
                            cy="75"
                            stroke-dasharray="{{ 408.4 * ($analytics['completion_forecast']['current_progress'] / 100) }} 408.4"
                            stroke-dashoffset="0"/>
                </svg>
                <h3 class="mt-3">{{ $analytics['completion_forecast']['current_progress'] }}%</h3>
                <p class="text-muted">現在の進捗率</p>
                
                <div class="text-start mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>週平均完了数</span>
                        <strong>{{ $analytics['completion_forecast']['weekly_average'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>予想完了週数</span>
                        <strong>{{ $analytics['completion_forecast']['estimated_weeks'] }}週</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>予想完了日</span>
                        <strong>{{ $analytics['completion_forecast']['estimated_date'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- 言語分布 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">言語別分布</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="languageDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- リスク分析 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">リスク分析</h5>
            </div>
            <div class="card-body">
                @forelse($analytics['risk_analysis'] as $risk)
                <div class="risk-item risk-{{ $risk['level'] }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">
                                @switch($risk['type'])
                                    @case('attendance')
                                        <i class="fas fa-calendar-times"></i> 出席
                                        @break
                                    @case('progress')
                                        <i class="fas fa-tasks"></i> 進捗
                                        @break
                                    @case('performance')
                                        <i class="fas fa-chart-line"></i> パフォーマンス
                                        @break
                                @endswitch
                            </h6>
                            <p class="mb-0 small">{{ $risk['message'] }}</p>
                        </div>
                        <span class="badge bg-{{ $risk['level'] === 'high' ? 'danger' : ($risk['level'] === 'medium' ? 'warning' : 'success') }}">
                            {{ $risk['level'] }}
                        </span>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center">リスクは検出されませんでした</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 進捗トレンドチャート
const progressCtx = document.getElementById('progressTrendChart').getContext('2d');
new Chart(progressCtx, {
    type: 'line',
    data: {!! json_encode($chartData['progress_trends']) !!},
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// 出席パターンチャート
const attendanceCtx = document.getElementById('attendanceByDayChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'bar',
    data: {!! json_encode($chartData['attendance_patterns']) !!},
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

// 言語分布チャート
const languageCtx = document.getElementById('languageDistributionChart').getContext('2d');
new Chart(languageCtx, {
    type: 'doughnut',
    data: {!! json_encode($chartData['language_distribution']) !!},
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
@endpush