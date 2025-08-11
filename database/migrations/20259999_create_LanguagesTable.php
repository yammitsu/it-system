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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('言語名');
            $table->string('code', 20)->unique()->comment('言語コード');
            $table->string('version', 20)->nullable()->comment('バージョン');
            $table->text('description')->nullable()->comment('説明');
            $table->string('icon', 255)->nullable()->comment('アイコンパス');
            $table->string('color_code', 7)->nullable()->comment('カラーコード');
            $table->integer('display_order')->default(0)->comment('表示順');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->json('metadata')->nullable()->comment('メタデータ');
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};