@extends('layouts.app')

@section('title', 'プロフィール')

@section('breadcrumb')
<li class="breadcrumb-item active">プロフィール</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-user-cog me-2"></i>プロフィール設定
        </h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- プロフィールカード -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                </div>
                <h4>{{ $user->name }}</h4>
                <p class="text-muted">{{ $user->role === 'student' ? '受講生' : '講師' }}</p>
                
                <hr>
                
                <div class="text-start">
                    <p><strong>企業:</strong> {{ $user->company->name ?? '-' }}</p>
                    <p><strong>学習言語:</strong> {{ $user->language->name ?? '-' }}</p>
                    <p><strong>社員番号:</strong> {{ $user->employee_number ?? '-' }}</p>
                    <p><strong>部署:</strong> {{ $user->department ?? '-' }}</p>
                    <p><strong>受講開始日:</strong> {{ $user->enrollment_date ? $user->enrollment_date->format('Y/m/d') : '-' }}</p>
                </div>
            </div>
        </div>
        
        <!-- 学習統計 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">学習統計</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>総学習時間</span>
                        <strong>{{ $stats['total_study_hours'] }}時間</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>連続学習日数</span>
                        <strong>{{ $stats['consecutive_days'] }}日</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>完了課題数</span>
                        <strong>{{ $stats['completed_tasks'] }}個</strong>
                    </div>
                </div>
                @if($stats['average_score'])
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>平均スコア</span>
                        <strong>{{ number_format($stats['average_score'], 1) }}点</strong>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- バッジ -->
        @if($badges->count() > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">獲得バッジ</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($badges as $badge)
                    <div class="col-4 text-center mb-3">
                        <div class="badge-icon">
                            @switch($badge->level)
                                @case('bronze')
                                    <i class="fas fa-medal fa-2x text-warning"></i>
                                    @break
                                @case('silver')
                                    <i class="fas fa-medal fa-2x text-secondary"></i>
                                    @break
                                @case('gold')
                                    <i class="fas fa-medal fa-2x text-warning"></i>
                                    @break
                                @case('platinum')
                                    <i class="fas fa-medal fa-2x text-info"></i>
                                    @break
                            @endswitch
                        </div>
                        <small class="d-block mt-1">{{ $badge->badge_name }}</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    
    <div class="col-lg-8">
        <!-- 基本情報編集 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">基本情報</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('student.profile.update') }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">氏名 <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">メールアドレス <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">※変更する場合はSlackの登録メールアドレスと同じにしてください</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">電話番号</label>
                            <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                   value="{{ old('phone', $user->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">部署</label>
                            <input type="text" name="department" class="form-control @error('department') is-invalid @enderror" 
                                   value="{{ old('department', $user->department) }}">
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">タイムゾーン</label>
                            <select name="timezone" class="form-select">
                                <option value="Asia/Tokyo" {{ $user->timezone == 'Asia/Tokyo' ? 'selected' : '' }}>
                                    Asia/Tokyo (JST)
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">言語設定</label>
                            <select name="locale" class="form-select">
                                <option value="ja" {{ $user->locale == 'ja' ? 'selected' : '' }}>日本語</option>
                                <option value="en" {{ $user->locale == 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>保存
                    </button>
                </form>
            </div>
        </div>
        
        <!-- パスワード変更 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">パスワード変更</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('student.profile.password') }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label">現在のパスワード <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">新しいパスワード <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        <small class="text-muted">※8文字以上、大小文字、数字、記号を含む</small>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">新しいパスワード（確認） <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>パスワードを変更
                    </button>
                </form>
            </div>
        </div>
        
        <!-- 通知設定 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">通知設定</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('student.profile.notifications') }}">
                    @csrf
                    @method('PUT')
                    
                    @php
                        $settings = $user->notification_settings ?? [];
                    @endphp
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="email_notification" 
                               name="email_notification" value="1"
                               {{ ($settings['email'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="email_notification">
                            <i class="fas fa-envelope me-2"></i>メール通知を受け取る
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="slack_notification" 
                               name="slack_notification" value="1"
                               {{ ($settings['slack'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="slack_notification">
                            <i class="fab fa-slack me-2"></i>Slack通知を受け取る
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="in_app_notification" 
                               name="in_app_notification" value="1"
                               {{ ($settings['in_app'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="in_app_notification">
                            <i class="fas fa-bell me-2"></i>アプリ内通知を受け取る
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>設定を保存
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection