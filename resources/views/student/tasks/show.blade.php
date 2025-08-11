{{-- resources/views/student/tasks/show.blade.php --}}
@extends('layouts.app')

@section('title', $task->title)

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('student.tasks.index') }}">課題一覧</a></li>
<li class="breadcrumb-item active">{{ $task->title }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- 課題情報 -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">{{ $task->title }}</h4>
            </div>
            <div class="card-body">
                <!-- バッジ -->
                <div class="mb-3">
                    <span class="badge bg-secondary">{{ $task->category_major }}</span>
                    @if($task->category_minor)
                    <span class="badge bg-light text-dark">{{ $task->category_minor }}</span>
                    @endif
                    
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
                    
                    @if($task->is_required)
                    <span class="badge bg-danger">必須</span>
                    @endif
                    
                    @if($task->company_id)
                    <span class="badge bg-info">企業専用</span>
                    @endif
                </div>
                
                <!-- 説明 -->
                <div class="mb-4">
                    <h5>説明</h5>
                    <p>{!! nl2br(e($task->description)) !!}</p>
                </div>
                
                <!-- 実施手順 -->
                @if($task->instructions)
                <div class="mb-4">
                    <h5>実施手順</h5>
                    <div class="bg-light p-3 rounded">
                        {!! nl2br(e($task->instructions)) !!}
                    </div>
                </div>
                @endif
                
                <!-- 前提条件 -->
                @if($task->prerequisites)
                <div class="mb-4">
                    <h5>前提条件</h5>
                    <p class="text-muted">{{ $task->prerequisites }}</p>
                </div>
                @endif
                
                <!-- 課題ファイル -->
                @if($task->file_path)
                <div class="mb-4">
                    <h5>課題ファイル</h5>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-archive fa-2x text-primary me-3"></i>
                        <div>
                            <div>{{ $task->file_name }}</div>
                            <small class="text-muted">
                                サイズ: {{ number_format($task->file_size / 1024 / 1024, 2) }} MB
                            </small>
                        </div>
                        <div class="ms-auto">
                            <a href="{{ route('student.tasks.download', $task) }}" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>ダウンロード
                            </a>
                        </div>
                    </div>
                    @if($submission && $submission->first_downloaded_at)
                    <small class="text-muted">
                        初回ダウンロード: {{ $submission->first_downloaded_at->format('Y/m/d H:i') }}
                        ({{ $submission->download_count }}回)
                    </small>
                    @endif
                </div>
                @endif
                
                <!-- タグ -->
                @if($task->tags)
                <div class="mb-4">
                    <h5>タグ</h5>
                    @foreach($task->tags as $tag)
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-tag me-1"></i>{{ $tag }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        
        <!-- 進捗管理 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">進捗管理</h5>
            </div>
            <div class="card-body">
                @if($submission)
                    <!-- ステータス表示 -->
                    <div class="mb-4">
                        <h6>現在のステータス</h6>
                        @switch($submission->status)
                            @case('completed')
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    この課題は完了しています
                                    <small class="d-block mt-2">
                                        完了日時: {{ $submission->completed_at->format('Y/m/d H:i') }}
                                    </small>
                                </div>
                                @break
                            @case('in_progress')
                                <div class="alert alert-warning">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    この課題は進行中です
                                    <small class="d-block mt-2">
                                        開始日時: {{ $submission->started_at->format('Y/m/d H:i') }}
                                    </small>
                                </div>
                                @break
                        @endswitch
                    </div>
                    
                    <!-- 進捗コメント更新 -->
                    @if($submission->status === 'in_progress')
                    <form method="POST" action="{{ route('student.tasks.progress', $task) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">進捗コメント</label>
                            <textarea name="progress_comment" class="form-control" rows="3" required>{{ $submission->progress_comment }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i>進捗を更新
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <!-- 完了ボタン -->
                    <form method="POST" action="{{ route('student.tasks.complete', $task) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">完了時メモ（任意）</label>
                            <textarea name="completion_note" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">実際の作業時間（分）</label>
                            <input type="number" name="actual_hours" class="form-control" min="1" max="999">
                        </div>
                        <button type="submit" class="btn btn-success" onclick="return confirm('この課題を完了しますか？')">
                            <i class="fas fa-check me-2"></i>課題を完了する
                        </button>
                    </form>
                    @endif
                    
                    <!-- フィードバック表示 -->
                    @if($submission->feedback)
                    <div class="mt-4">
                        <h6>講師からのフィードバック</h6>
                        <div class="bg-light p-3 rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>スコア: {{ $submission->score }}/100</strong>
                                @if($submission->evaluated_at)
                                <small class="text-muted">
                                    評価日: {{ $submission->evaluated_at->format('Y/m/d') }}
                                </small>
                                @endif
                            </div>
                            <p class="mb-0">{{ $submission->feedback }}</p>
                        </div>
                    </div>
                    @endif
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <p class="text-muted">まず課題ファイルをダウンロードして開始してください</p>
                        @if($task->file_path)
                        <a href="{{ route('student.tasks.download', $task) }}" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>課題をダウンロードして開始
                        </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- 課題情報サマリー -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">課題情報</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>推定時間:</th>
                        <td>{{ $task->estimated_hours }}時間</td>
                    </tr>
                    <tr>
                        <th>獲得ポイント:</th>
                        <td>{{ $task->points }}pt</td>
                    </tr>
                    <tr>
                        <th>完了者数:</th>
                        <td>{{ $task->completion_count }}名</td>
                    </tr>
                    @if($task->average_completion_time)
                    <tr>
                        <th>平均完了時間:</th>
                        <td>{{ number_format($task->average_completion_time, 1) }}時間</td>
                    </tr>
                    @endif
                    @if($task->average_rating)
                    <tr>
                        <th>平均評価:</th>
                        <td>
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $task->average_rating)
                                <i class="fas fa-star text-warning"></i>
                                @else
                                <i class="far fa-star text-warning"></i>
                                @endif
                            @endfor
                            ({{ number_format($task->average_rating, 1) }})
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        
        <!-- 依存関係 -->
        @if($dependencies->count() > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">前提課題</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @foreach($dependencies as $dep)
                    <li class="mb-2">
                        <a href="{{ route('student.tasks.show', $dep) }}" class="text-decoration-none">
                            @if($dep->submissions->first() && $dep->submissions->first()->status === 'completed')
                            <i class="fas fa-check-circle text-success me-2"></i>
                            @else
                            <i class="fas fa-times-circle text-danger me-2"></i>
                            @endif
                            {{ $dep->title }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
        
        <!-- 他の受講生の進捗 -->
        @if($peerProgress->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">他の受講生の進捗</h5>
            </div>
            <div class="card-body">
                @foreach($peerProgress as $progress)
                <div class="mb-2">
                    @switch($progress->status)
                        @case('completed')
                            <span class="badge bg-success">完了</span>
                            @break
                        @case('in_progress')
                            <span class="badge bg-warning">進行中</span>
                            @break
                        @case('not_started')
                            <span class="badge bg-secondary">未着手</span>
                            @break
                    @endswitch
                    <span class="ms-2">{{ $progress->count }}名</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection