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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('ユーザーID');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null')->comment('企業ID');
            $table->string('event_type', 100)->comment('イベントタイプ');
            $table->string('model_type', 100)->nullable()->comment('モデルタイプ');
            $table->unsignedBigInteger('model_id')->nullable()->comment('モデルID');
            $table->string('action', 50)->comment('アクション（create, update, delete, etc）');
            $table->text('description')->nullable()->comment('説明');
            $table->json('old_values')->nullable()->comment('変更前の値');
            $table->json('new_values')->nullable()->comment('変更後の値');
            $table->string('ip_address', 45)->nullable()->comment('IPアドレス');
            $table->text('user_agent')->nullable()->comment('ユーザーエージェント');
            $table->string('session_id', 255)->nullable()->comment('セッションID');
            $table->string('request_method', 10)->nullable()->comment('リクエストメソッド');
            $table->string('request_url', 500)->nullable()->comment('リクエストURL');
            $table->json('request_data')->nullable()->comment('リクエストデータ');
            $table->integer('response_code')->nullable()->comment('レスポンスコード');
            $table->decimal('execution_time', 10, 3)->nullable()->comment('実行時間（秒）');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('user_id');
            $table->index('company_id');
            $table->index('event_type');
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('created_at');
            $table->index('ip_address');
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};