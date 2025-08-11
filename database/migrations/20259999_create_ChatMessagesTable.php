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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade')->comment('送信者ID');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade')->comment('受信者ID');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade')->comment('関連課題ID');
            $table->foreignId('parent_message_id')->nullable()->constrained('chat_messages')->onDelete('cascade')->comment('親メッセージID（スレッド）');
            $table->enum('type', ['question', 'answer', 'comment', 'announcement'])->default('comment')->comment('メッセージタイプ');
            $table->text('message')->comment('メッセージ内容');
            $table->boolean('is_read')->default(false)->comment('既読フラグ');
            $table->timestamp('read_at')->nullable()->comment('既読日時');
            $table->boolean('is_resolved')->default(false)->comment('解決済みフラグ');
            $table->timestamp('resolved_at')->nullable()->comment('解決日時');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null')->comment('解決者ID');
            $table->boolean('is_pinned')->default(false)->comment('ピン留めフラグ');
            $table->boolean('is_edited')->default(false)->comment('編集済みフラグ');
            $table->timestamp('edited_at')->nullable()->comment('編集日時');
            $table->json('attachments')->nullable()->comment('添付ファイル');
            $table->json('mentions')->nullable()->comment('メンション');
            $table->integer('likes_count')->default(0)->comment('いいね数');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('sender_id');
            $table->index('recipient_id');
            $table->index('task_id');
            $table->index('parent_message_id');
            $table->index('type');
            $table->index('is_read');
            $table->index('is_resolved');
            $table->index('is_pinned');
            $table->index(['sender_id', 'recipient_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};