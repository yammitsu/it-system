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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade')->comment('講師ID');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade')->comment('担当企業ID');
            $table->foreignId('language_id')->nullable()->constrained()->onDelete('set null')->comment('担当言語ID');
            $table->date('shift_date')->comment('シフト日');
            $table->time('start_time')->default('09:00:00')->comment('開始時刻');
            $table->time('end_time')->default('18:00:00')->comment('終了時刻');
            $table->enum('status', ['scheduled', 'confirmed', 'cancelled', 'completed'])->default('scheduled')->comment('ステータス');
            $table->string('slack_channel_id', 50)->nullable()->comment('Slackチャンネル名');
            $table->boolean('slack_channel_created')->default(false)->comment('Slackチャンネル作成済みフラグ');
            $table->integer('max_students')->default(20)->comment('最大受講生数');
            $table->integer('current_students')->default(0)->comment('現在の受講生数');
            $table->text('notes')->nullable()->comment('備考');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            
            $table->unique(['teacher_id', 'shift_date']);
            $table->index('teacher_id');
            $table->index('company_id');
            $table->index('language_id');
            $table->index('shift_date');
            $table->index('status');
            $table->index(['shift_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};