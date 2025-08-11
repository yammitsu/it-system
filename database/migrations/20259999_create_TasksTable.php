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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->onDelete('cascade')->comment('言語ID');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade')->comment('企業ID（企業専用課題の場合）');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict')->comment('作成者ID');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('cascade')->comment('親課題ID');
            $table->string('category_major', 255)->comment('大区分');
            $table->string('category_minor', 255)->nullable()->comment('小区分');
            $table->string('title', 500)->comment('課題タイトル');
            $table->text('description')->nullable()->comment('課題説明');
            $table->text('instructions')->nullable()->comment('実施手順');
            $table->text('prerequisites')->nullable()->comment('前提条件');
            $table->string('file_path', 500)->nullable()->comment('課題ファイルパス');
            $table->string('file_name', 255)->nullable()->comment('ファイル名');
            $table->integer('file_size')->nullable()->comment('ファイルサイズ（バイト）');
            $table->string('file_mime_type', 100)->nullable()->comment('MIMEタイプ');
            $table->timestamp('file_scanned_at')->nullable()->comment('ウイルススキャン日時');
            $table->boolean('file_is_safe')->default(true)->comment('ファイル安全性フラグ');
            $table->integer('estimated_hours')->default(1)->comment('推定所要時間（時間）');
            $table->integer('points')->default(10)->comment('獲得ポイント');
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced', 'expert'])->default('beginner')->comment('難易度');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->boolean('is_required')->default(false)->comment('必須フラグ');
            $table->boolean('is_template')->default(false)->comment('テンプレートフラグ');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->date('available_from')->nullable()->comment('公開開始日');
            $table->date('available_until')->nullable()->comment('公開終了日');
            $table->json('tags')->nullable()->comment('タグ');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->integer('completion_count')->default(0)->comment('完了者数');
            $table->decimal('average_completion_time', 10, 2)->nullable()->comment('平均完了時間（時間）');
            $table->decimal('average_rating', 3, 2)->nullable()->comment('平均評価');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('language_id');
            $table->index('company_id');
            $table->index('created_by');
            $table->index('parent_task_id');
            $table->index(['category_major', 'category_minor']);
            $table->index('difficulty');
            $table->index('display_order');
            $table->index('is_active');
            $table->index(['available_from', 'available_until']);
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};