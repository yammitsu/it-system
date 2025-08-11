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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('フィードバック送信者ID');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade')->comment('対象ユーザーID');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade')->comment('対象課題ID');
            $table->foreignId('learning_path_id')->nullable()->constrained()->onDelete('cascade')->comment('対象学習パスID');
            $table->enum('type', [
                'task_feedback',
                'course_feedback',
                'teacher_evaluation',
                'student_evaluation',
                'system_feedback',
                'bug_report',
                'feature_request'
            ])->comment('フィードバックタイプ');
            $table->string('title', 255)->nullable()->comment('タイトル');
            $table->text('content')->comment('内容');
            $table->integer('rating')->nullable()->comment('評価（1-5）');
            $table->json('ratings_detail')->nullable()->comment('詳細評価（複数項目）');
            $table->enum('status', ['pending', 'in_review', 'resolved', 'closed'])->default('pending')->comment('ステータス');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->comment('優先度');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('担当者ID');
            $table->text('response')->nullable()->comment('回答');
            $table->timestamp('responded_at')->nullable()->comment('回答日時');
            $table->boolean('is_public')->default(false)->comment('公開フラグ');
            $table->boolean('is_anonymous')->default(false)->comment('匿名フラグ');
            $table->json('attachments')->nullable()->comment('添付ファイル');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('task_id');
            $table->index('learning_path_id');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};