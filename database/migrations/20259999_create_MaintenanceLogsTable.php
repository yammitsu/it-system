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
        // メンテナンスログテーブル
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->comment('メンテナンスタイプ');
            $table->text('description')->comment('説明');
            $table->timestamp('started_at')->comment('開始日時');
            $table->timestamp('completed_at')->nullable()->comment('完了日時');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->comment('ステータス');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null')->comment('実施者');
            $table->json('affected_services')->nullable()->comment('影響サービス');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            
            $table->index('type');
            $table->index('status');
            $table->index(['started_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};