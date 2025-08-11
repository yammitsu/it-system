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
        // Slackチャンネル管理テーブル
        Schema::create('slack_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id', 50)->unique()->comment('SlackチャンネルID');
            $table->string('channel_name', 255)->comment('チャンネル名');
            $table->date('date')->comment('対象日付');
            $table->foreignId('shift_id')->nullable()->constrained()->onDelete('set null')->comment('シフトID');
            $table->enum('type', ['daily', 'general', 'announcement'])->default('daily')->comment('チャンネルタイプ');
            $table->boolean('is_archived')->default(false)->comment('アーカイブフラグ');
            $table->timestamp('created_at_slack')->nullable()->comment('Slack作成日時');
            $table->json('members')->nullable()->comment('メンバーリスト');
            $table->timestamps();
            
            $table->index('channel_id');
            $table->index('date');
            $table->index('shift_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_channels');
    }
};