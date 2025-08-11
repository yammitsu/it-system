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
        // ファイルダウンロード履歴テーブル
        Schema::create('file_download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('task_id')->constrained()->onDelete('cascade')->comment('課題ID');
            $table->string('file_name', 255)->comment('ファイル名');
            $table->string('file_path', 500)->comment('ファイルパス');
            $table->integer('file_size')->comment('ファイルサイズ');
            $table->string('ip_address', 45)->nullable()->comment('IPアドレス');
            $table->text('user_agent')->nullable()->comment('ユーザーエージェント');
            $table->timestamp('downloaded_at')->useCurrent()->comment('ダウンロード日時');
            
            $table->index('user_id');
            $table->index('task_id');
            $table->index('downloaded_at');
            $table->index(['user_id', 'task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_download_logs');
    }
};