<?php

// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\TaskController as StudentTaskController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\TaskController as TeacherTaskController;
use App\Http\Controllers\Teacher\ShiftController as TeacherShiftController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SystemSettingController as AdminSystemSettingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// リダイレクト
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        switch ($user->role) {
            case 'system_admin':
                return redirect('/admin/dashboard');
            case 'company_admin':
                return redirect('/company/dashboard');
            case 'teacher':
                return redirect('/teacher/dashboard');
            case 'student':
                return redirect('/student/dashboard');
        }
    }
    return redirect('/login');
});

// 認証関連
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// 受講生用ルート
Route::middleware(['auth', 'check.session', 'check.role:student', 'check.company', 'log.activity'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        
        // 課題管理
        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', [StudentTaskController::class, 'index'])->name('index');
            Route::get('/{task}', [StudentTaskController::class, 'show'])->name('show');
            Route::get('/{task}/download', [StudentTaskController::class, 'download'])->name('download');
            Route::post('/{task}/complete', [StudentTaskController::class, 'complete'])->name('complete');
            Route::post('/{task}/progress', [StudentTaskController::class, 'updateProgress'])->name('progress');
        });
        
        // 出席管理
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [StudentAttendanceController::class, 'index'])->name('index');
            Route::post('/register', [StudentAttendanceController::class, 'register'])->name('register');
            Route::post('/cancel', [StudentAttendanceController::class, 'cancel'])->name('cancel');
        });
        
        // プロフィール
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [StudentProfileController::class, 'index'])->name('index');
            Route::put('/update', [StudentProfileController::class, 'update'])->name('update');
            Route::put('/password', [StudentProfileController::class, 'updatePassword'])->name('password');
        });
    });

// 講師用ルート
Route::middleware(['auth', 'check.session', 'check.role:teacher,company_admin', 'check.company', 'log.activity'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
        
        // 課題管理
        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', [TeacherTaskController::class, 'index'])->name('index');
            Route::get('/create', [TeacherTaskController::class, 'create'])->name('create');
            Route::post('/', [TeacherTaskController::class, 'store'])->name('store');
            Route::get('/{task}', [TeacherTaskController::class, 'show'])->name('show');
            Route::get('/{task}/edit', [TeacherTaskController::class, 'edit'])->name('edit');
            Route::put('/{task}', [TeacherTaskController::class, 'update'])->name('update');
            Route::delete('/{task}', [TeacherTaskController::class, 'destroy'])->name('destroy');
            Route::get('/{task}/submissions', [TeacherTaskController::class, 'submissions'])->name('submissions');
            Route::post('/{task}/submissions/{submission}/evaluate', [TeacherTaskController::class, 'evaluate'])->name('evaluate');
        });
        
        // シフト管理
        Route::prefix('shifts')->name('shifts.')->group(function () {
            Route::get('/', [TeacherShiftController::class, 'index'])->name('index');
            Route::get('/calendar', [TeacherShiftController::class, 'calendar'])->name('calendar');
            Route::post('/', [TeacherShiftController::class, 'store'])->name('store');
            Route::put('/{shift}', [TeacherShiftController::class, 'update'])->name('update');
            Route::delete('/{shift}', [TeacherShiftController::class, 'destroy'])->name('destroy');
        });
        
        // 受講生管理
        Route::prefix('students')->name('students.')->group(function () {
            Route::get('/', [TeacherStudentController::class, 'index'])->name('index');
            Route::get('/{user}', [TeacherStudentController::class, 'show'])->name('show');
            Route::get('/{user}/progress', [TeacherStudentController::class, 'progress'])->name('progress');
            Route::post('/import', [TeacherStudentController::class, 'import'])->name('import');
            Route::get('/export', [TeacherStudentController::class, 'export'])->name('export');
        });
    });

// 企業管理者用ルート
Route::middleware(['auth', 'check.session', 'check.role:company_admin', 'check.company', 'log.activity'])
    ->prefix('company')
    ->name('company.')
    ->group(function () {
        // ダッシュボードは講師と共通
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
        
        // その他の機能は講師ルートを利用
    });

// システム管理者用ルート
Route::middleware(['auth', 'check.session', 'check.role:system_admin', 'log.activity'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // ダッシュボード
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // 企業管理
        Route::prefix('companies')->name('companies.')->group(function () {
            Route::get('/', [AdminCompanyController::class, 'index'])->name('index');
            Route::get('/create', [AdminCompanyController::class, 'create'])->name('create');
            Route::post('/', [AdminCompanyController::class, 'store'])->name('store');
            Route::get('/{company}', [AdminCompanyController::class, 'show'])->name('show');
            Route::get('/{company}/edit', [AdminCompanyController::class, 'edit'])->name('edit');
            Route::put('/{company}', [AdminCompanyController::class, 'update'])->name('update');
            Route::delete('/{company}', [AdminCompanyController::class, 'destroy'])->name('destroy');
        });
        
        // ユーザー管理
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/create', [AdminUserController::class, 'create'])->name('create');
            Route::post('/', [AdminUserController::class, 'store'])->name('store');
            Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
            Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [AdminUserController::class, 'update'])->name('update');
            Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
            Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('reset-password');
        });
        
        // システム設定
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [AdminSystemSettingController::class, 'index'])->name('index');
            Route::put('/', [AdminSystemSettingController::class, 'update'])->name('update');
            Route::get('/slack', [AdminSystemSettingController::class, 'slack'])->name('slack');
            Route::post('/slack/test', [AdminSystemSettingController::class, 'testSlack'])->name('slack.test');
        });
    });