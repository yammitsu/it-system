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
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->onDelete('cascade')->comment('言語ID');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade')->comment('企業ID（企業専用パスの場合）');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict')->comment('作成者ID');
            $table->string('name', 255)->comment('パス名');
            $table->text('description')->nullable()->comment('説明');
            $table->string('slug', 100)->unique()->comment('スラッグ');
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('beginner')->comment('レベル');
            $table->integer('estimated_weeks')->default(4)->comment('推定週数');
            $table->json('objectives')->nullable()->comment('学習目標');
            $table->json('prerequisites')->nullable()->comment('前提条件');
            $table->json('task_ids')->nullable()->comment('課題IDリスト（順序付き）');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->boolean('is_recommended')->default(false)->comment('推奨フラグ');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->integer('enrollment_count')->default(0)->comment('登録者数');
            $table->integer('completion_count')->default(0)->comment('完了者数');
            $table->decimal('average_rating', 3, 2)->nullable()->comment('平均評価');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('language_id');
            $table->index('company_id');
            $table->index('created_by');
            $table->index('slug');
            $table->index('level');
            $table->index('is_active');
            $table->index('is_recommended');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};