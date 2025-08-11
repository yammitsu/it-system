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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null')->comment('送信者ID');
            $table->enum('type', [
                'system', 
                'task_assigned', 
                'task_completed', 
                'attendance_reminder',
                'shift_assigned',
                'feedback_received',
                'announcement',
                'warning',
                'achievement'
            ])->comment('通知タイプ');
            $table->enum('channel', ['in_app', 'email', 'slack', 'push'])->default('in_app')->comment('通知チャンネル');
            $table->string('title', 255)->comment('タイトル');
            $table->text('message')->comment('メッセージ');
            $table->string('action_url', 500)->nullable()->comment('アクションURL');
            $table->string('icon', 100)->nullable()->comment('アイコン');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->comment('優先度');
            $table->boolean('is_read')->default(false)->comment('既読フラグ');
            $table->timestamp('read_at')->nullable()->comment('既読日時');
            $table->boolean('is_sent')->default(false)->comment('送信済みフラグ');
            $table->timestamp('sent_at')->nullable()->comment('送信日時');
            $table->json('data')->nullable()->comment('追加データ');
            $table->timestamp('scheduled_at')->nullable()->comment('予定送信日時');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('sender_id');
            $table->index('type');
            $table->index('channel');
            $table->index('is_read');
            $table->index('priority');
            $table->index(['user_id', 'is_read']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};