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
        // API利用制限テーブル
        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->comment('制限キー（IP/ユーザーID）');
            $table->string('route', 255)->comment('ルート');
            $table->integer('attempts')->default(0)->comment('試行回数');
            $table->timestamp('reset_at')->comment('リセット時刻');
            $table->timestamps();
            
            $table->unique(['key', 'route']);
            $table->index('key');
            $table->index('reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_rate_limits');
    }
};