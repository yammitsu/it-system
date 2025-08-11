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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->string('session_id', 255)->unique()->comment('セッションID');
            $table->string('ip_address', 45)->nullable()->comment('IPアドレス');
            $table->text('user_agent')->nullable()->comment('ユーザーエージェント');
            $table->string('device_type', 50)->nullable()->comment('デバイスタイプ');
            $table->string('browser', 50)->nullable()->comment('ブラウザ');
            $table->string('platform', 50)->nullable()->comment('プラットフォーム');
            $table->timestamp('last_activity')->comment('最終アクティビティ');
            $table->timestamp('expires_at')->comment('有効期限');
            $table->boolean('is_active')->default(true)->comment('アクティブフラグ');
            $table->json('payload')->nullable()->comment('セッションデータ');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('session_id');
            $table->index('is_active');
            $table->index('expires_at');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};