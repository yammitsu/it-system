{{-- resources/views/student/tasks/index.blade.php --}}
@extends('layouts.app')

@section('title', '課題一覧')

@section('breadcrumb')
<li class="breadcrumb-item active">課題一覧</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-tasks me-2"></i>課題一覧
            </h1>
            <div>
                <span class="badge bg-primary">{{ auth()->user()->language->name }}</span>
            </div>
        </div>
    </div>
</div>

<!-- 進捗サマリー -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">全課題</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $stats['total'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">完了</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $stats['completed'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">進行中</div>
                        <div class="h5 mb-0 font-weight-bold">{{ $stats['in_progress'] }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-spinner fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">進捗率</div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold">{{ $stats['completion_rate'] }}%</div>
                            </div>
                            <div class="col">
                                <div class="progress progress-sm">
                                    <div class="progress-bar bg-info" style="width: {{ $stats['completion_rate'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- フィルター -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('student.tasks.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">カテゴリ</label>
                <select name="category" class="form-select">
                    <option value="">すべて</option>
                    <option value="基礎編" {{ request('category') == '基礎編' ? 'selected' : '' }}>基礎編</option>
                    <option value="応用編" {{ request('category') == '応用編' ? 'selected' : '' }}>応用編</option>
                    <option value="実践編" {{ request('category') == '実践編' ? 'selected' : '' }}>実践編</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">難易度</label>
                <select name="difficulty" class="form-select">
                    <option value="">すべて</option>
                    <option value="beginner" {{ request('difficulty') == 'beginner' ? 'selected' : '' }}>初級</option>
                    <option value="intermediate" {{ request('difficulty') == 'intermediate' ? 'selected' : '' }}>中級</option>
                    <option value="advanced" {{ request('difficulty') == 'advanced' ? 'selected' : '' }}>上級</option>
                    <option value="expert" {{ request('difficulty') == 'expert' ? 'selected' : '' }}>エキスパート</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">ステータス</label>
                <select name="status" class="form-select">
                    <option value="">すべて</option>
                    <option value="not_started" {{ request('status') == 'not_started' ? 'selected' : '' }}>未着手</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>進行中</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>完了</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>検索
                    </button>
                    <a href="{{ route('student.tasks.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>リセット
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 課題リスト -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>カテゴリ</th>
                        <th>課題名</th>
                        <th>難易度</th>
                        <th>推定時間</th>
                        <th>ポイント</th>
                        <th>ステータス</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr>
                        <td>
                            <span class="badge bg-secondary">{{ $task->category_major }}</span>
                            @if($task->category_minor)
                            <br><small class="text-muted">{{ $task->category_minor }}</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('student.tasks.show', $task) }}" class="text-decoration-none">
                                {{ $task->title }}
                            </a>
                            @if($task->is_required)
                            <span class="badge bg-danger ms-2">必須</span>
                            @endif
                            @if($task->company_id)
                            <span class="badge bg-info ms-2">企業専用</span>
                            @endif
                        </td>
                        <td>
                            @switch($task->difficulty)
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
                        <td>{{ $task->estimated_hours }}時間</td>
                        <td>{{ $task->points }}pt</td>
                        <td>
                            @php
                                $submission = $task->submissions->first();
                            @endphp
                            @if($submission)
                                @switch($submission->status)
                                    @case('completed')
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>完了
                                        </span>
                                        @break
                                    @case('in_progress')
                                        <span class="badge bg-warning">
                                            <i class="fas fa-spinner fa-spin me-1"></i>進行中
                                        </span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">未着手</span>
                                @endswitch
                            @else
                                <span class="badge bg-secondary">未着手</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('student.tasks.show', $task) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> 詳細
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">課題がありません</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <div class="d-flex justify-content-center">
            {{ $tasks->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 進捗バーのアニメーション
    document.addEventListener('DOMContentLoaded', function() {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1s ease-in-out';
                bar.style.width = width;
            }, 100);
        });
    });
</script>
@endpush