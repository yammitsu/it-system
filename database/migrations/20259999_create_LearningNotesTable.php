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
        // 学習ノートテーブル
        Schema::create('learning_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade')->comment('関連課題ID');
            $table->string('title', 255)->comment('タイトル');
            $table->text('content')->comment('内容');
            $table->json('tags')->nullable()->comment('タグ');
            $table->boolean('is_public')->default(false)->comment('公開フラグ');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('task_id');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_notes');
    }
};