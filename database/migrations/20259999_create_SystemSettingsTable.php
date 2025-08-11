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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->comment('設定カテゴリ');
            $table->string('key', 100)->comment('設定キー');
            $table->text('value')->nullable()->comment('設定値');
            $table->string('type', 50)->default('string')->comment('値の型（string, integer, boolean, json）');
            $table->text('description')->nullable()->comment('説明');
            $table->json('options')->nullable()->comment('選択肢（選択型の場合）');
            $table->boolean('is_public')->default(false)->comment('公開設定フラグ');
            $table->boolean('is_editable')->default(true)->comment('編集可能フラグ');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('最終更新者');
            $table->timestamps();
            
            $table->unique(['category', 'key']);
            $table->index('category');
            $table->index('key');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};