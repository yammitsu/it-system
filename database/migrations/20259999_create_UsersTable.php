<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade')->comment('企業ID');
            $table->foreignId('language_id')->nullable()->constrained()->onDelete('set null')->comment('学習言語ID');
            $table->string('name', 255)->comment('氏名');
            $table->string('email', 255)->unique()->comment('メールアドレス');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('slack_user_id', 50)->nullable()->unique()->comment('Slack ユーザーID');
            $table->string('slack_email', 255)->nullable()->comment('Slack登録メール');
            $table->enum('role', ['system_admin', 'company_admin', 'teacher', 'student'])->default('student')->comment('権限');
            $table->string('employee_number', 50)->nullable()->comment('社員番号');
            $table->string('department', 255)->nullable()->comment('部署');
            $table->string('position', 255)->nullable()->comment('役職');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('avatar', 500)->nullable()->comment('アバター画像パス');
            $table->date('enrollment_date')->nullable()->comment('受講開始日');
            $table->date('completion_date')->nullable()->comment('受講完了日');
            $table->enum('status', ['active', 'inactive', 'suspended', 'completed'])->default('active')->comment('ステータス');
            $table->integer('total_study_hours')->default(0)->comment('総学習時間（分）');
            $table->integer('consecutive_days')->default(0)->comment('連続学習日数');
            $table->date('last_login_at')->nullable()->comment('最終ログイン日時');
            $table->string('last_login_ip', 45)->nullable()->comment('最終ログインIP');
            $table->json('notification_settings')->nullable()->comment('通知設定');
            $table->json('preferences')->nullable()->comment('ユーザー設定');
            $table->string('timezone', 50)->default('Asia/Tokyo')->comment('タイムゾーン');
            $table->string('locale', 10)->default('ja')->comment('言語設定');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('company_id');
            $table->index('language_id');
            $table->index('email');
            $table->index('slack_user_id');
            $table->index('role');
            $table->index('status');
            $table->index(['company_id', 'role']);
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};