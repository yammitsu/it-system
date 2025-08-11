<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IT言語別スキル取得講習システム')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-top {
            height: var(--header-height);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background-color: #2c3e50;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: calc(100vh - var(--header-height));
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .sidebar-nav {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        
        .sidebar-nav li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav a {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-nav a:hover {
            background-color: #34495e;
            padding-left: 25px;
        }
        
        .sidebar-nav a.active {
            background-color: #3498db;
            border-left: 4px solid #2980b9;
        }
        
        .sidebar-nav i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #f1f3f4;
            font-weight: 600;
        }
        
        .progress-circle {
            width: 150px;
            height: 150px;
            position: relative;
        }
        
        .stat-card {
            border-left: 4px solid;
            padding: 20px;
            background: white;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                width: var(--sidebar-width);
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-top fixed-top">
        <div class="container-fluid">
            <button class="btn btn-link text-white" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand ms-3" href="{{ route('student.dashboard') }}">
                <i class="fas fa-graduation-cap"></i> IT講習システム
            </a>
            
            <div class="ms-auto d-flex align-items-center">
                <!-- 通知 -->
                <div class="dropdown me-3">
                    <button class="btn btn-link text-white position-relative" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        @if(auth()->user()->notifications()->unread()->count() > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            {{ auth()->user()->notifications()->unread()->count() }}
                        </span>
                        @endif
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">通知</h6></li>
                        @forelse(auth()->user()->notifications()->unread()->limit(5)->get() as $notification)
                        <li>
                            <a class="dropdown-item" href="#">
                                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small><br>
                                {{ $notification->title }}
                            </a>
                        </li>
                        @empty
                        <li><span class="dropdown-item text-muted">新しい通知はありません</span></li>
                        @endforelse
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">すべて見る</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <ul class="sidebar-nav">
            @if(auth()->user()->role === 'student')
                <li>
                    <a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">ダッシュボード</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('student.tasks.index') }}" class="{{ request()->routeIs('student.tasks.*') ? 'active' : '' }}">
                        <i class="fas fa-tasks"></i>
                        <span class="nav-text">課題管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('student.attendance.index') }}" class="{{ request()->routeIs('student.attendance.*') ? 'active' : '' }}">
                        <i class="fas fa-calendar-check"></i>
                        <span class="nav-text">出席管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('student.profile.index') }}" class="{{ request()->routeIs('student.profile.*') ? 'active' : '' }}">
                        <i class="fas fa-user-cog"></i>
                        <span class="nav-text">プロフィール</span>
                    </a>
                </li>
            @elseif(in_array(auth()->user()->role, ['teacher', 'company_admin']))
                <li>
                    <a href="{{ route('teacher.dashboard') }}" class="{{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">ダッシュボード</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('teacher.tasks.index') }}" class="{{ request()->routeIs('teacher.tasks.*') ? 'active' : '' }}">
                        <i class="fas fa-book"></i>
                        <span class="nav-text">課題管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('teacher.students.index') }}" class="{{ request()->routeIs('teacher.students.*') ? 'active' : '' }}">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">受講生管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('teacher.shifts.index') }}" class="{{ request()->routeIs('teacher.shifts.*') ? 'active' : '' }}">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">シフト管理</span>
                    </a>
                </li>
            @elseif(auth()->user()->role === 'system_admin')
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">ダッシュボード</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.companies.index') }}" class="{{ request()->routeIs('admin.companies.*') ? 'active' : '' }}">
                        <i class="fas fa-building"></i>
                        <span class="nav-text">企業管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">ユーザー管理</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">システム設定</span>
                    </a>
                </li>
            @endif
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="container-fluid">
            <!-- Breadcrumb -->
            @if(!request()->routeIs('*.dashboard'))
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route(auth()->user()->role . '.dashboard') }}">ホーム</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
            @endif

            <!-- Flash Messages -->
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>{{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <!-- Page Content -->
            @yield('content')
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        // Mobile Sidebar
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.add('collapsed');
            document.getElementById('mainContent').classList.add('expanded');
        }

        // CSRF Token for AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
                </div>
                
                <!-- ユーザーメニュー -->
                <div class="dropdown">
                    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ auth()->user()->role }}</h6></li>
                        <li><a class="dropdown-item" href="{{ route('student.profile.index') }}">
                            <i class="fas fa-user"></i> プロフィール
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> ログアウト
                                </button>
                            </form>
                        </li>
                    </ul>