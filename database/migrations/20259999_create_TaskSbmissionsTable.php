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
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('task_id')->constrained()->onDelete('cascade')->comment('課題ID');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->onDelete('set null')->comment('評価者ID');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'skipped'])->default('not_started')->comment('ステータス');
            $table->timestamp('first_downloaded_at')->nullable()->comment('初回ダウンロード日時');
            $table->timestamp('started_at')->nullable()->comment('開始日時');
            $table->timestamp('completed_at')->nullable()->comment('完了日時');
            $table->integer('actual_hours')->nullable()->comment('実際の所要時間（分）');
            $table->text('progress_comment')->nullable()->comment('進捗コメント');
            $table->text('completion_note')->nullable()->comment('完了時メモ');
            $table->integer('rating')->nullable()->comment('評価（1-5）');
            $table->text('feedback')->nullable()->comment('フィードバック');
            $table->integer('score')->nullable()->comment('スコア');
            $table->text('evaluation_comment')->nullable()->comment('評価コメント');
            $table->timestamp('evaluated_at')->nullable()->comment('評価日時');
            $table->integer('download_count')->default(0)->comment('ダウンロード回数');
            $table->json('submission_files')->nullable()->comment('提出ファイル情報');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            
            $table->unique(['user_id', 'task_id']);
            $table->index('user_id');
            $table->index('task_id');
            $table->index('status');
            $table->index('completed_at');
            $table->index('evaluated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_submissions');
    }
};