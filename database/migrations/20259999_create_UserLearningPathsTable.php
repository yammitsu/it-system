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
        // ユーザー学習パス登録テーブル
        Schema::create('user_learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('learning_path_id')->constrained()->onDelete('cascade')->comment('学習パスID');
            $table->timestamp('enrolled_at')->comment('登録日時');
            $table->timestamp('completed_at')->nullable()->comment('完了日時');
            $table->integer('progress_percentage')->default(0)->comment('進捗率');
            $table->integer('completed_tasks')->default(0)->comment('完了課題数');
            $table->integer('total_tasks')->default(0)->comment('総課題数');
            $table->timestamps();
            
            $table->unique(['user_id', 'learning_path_id']);
            $table->index('user_id');
            $table->index('learning_path_id');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_learning_paths');
    }
};