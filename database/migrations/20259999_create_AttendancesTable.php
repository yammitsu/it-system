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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->date('attendance_date')->comment('出席日');
            $table->enum('status', ['present', 'absent', 'cancelled', 'late'])->default('present')->comment('出席ステータス');
            $table->time('check_in_time')->nullable()->comment('チェックイン時刻');
            $table->time('check_out_time')->nullable()->comment('チェックアウト時刻');
            $table->integer('study_minutes')->default(0)->comment('学習時間（分）');
            $table->string('slack_channel_id', 50)->nullable()->comment('Slackチャンネル名');
            $table->boolean('slack_invited')->default(false)->comment('Slack招待済みフラグ');
            $table->timestamp('slack_invited_at')->nullable()->comment('Slack招待日時');
            $table->text('notes')->nullable()->comment('備考');
            $table->string('ip_address', 45)->nullable()->comment('IPアドレス');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            
            $table->unique(['user_id', 'attendance_date']);
            $table->index('user_id');
            $table->index('attendance_date');
            $table->index('status');
            $table->index(['attendance_date', 'status']);
            $table->index('slack_channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};