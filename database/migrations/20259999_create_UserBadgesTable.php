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
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ユーザーID');
            $table->string('badge_type', 100)->comment('バッジタイプ');
            $table->string('badge_name', 255)->comment('バッジ名');
            $table->text('description')->nullable()->comment('説明');
            $table->string('icon', 255)->nullable()->comment('アイコンパス');
            $table->enum('level', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze')->comment('レベル');
            $table->integer('points')->default(0)->comment('獲得ポイント');
            $table->json('criteria')->nullable()->comment('達成条件');
            $table->json('progress')->nullable()->comment('進捗データ');
            $table->timestamp('earned_at')->comment('獲得日時');
            $table->boolean('is_featured')->default(false)->comment('フィーチャーフラグ');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('badge_type');
            $table->index('level');
            $table->index('earned_at');
            $table->index(['user_id', 'badge_type']);
            $table->unique(['user_id', 'badge_type', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};