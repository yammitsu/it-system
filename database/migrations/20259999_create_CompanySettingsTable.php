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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->comment('企業ID');
            $table->string('category', 100)->comment('設定カテゴリ');
            $table->string('key', 100)->comment('設定キー');
            $table->text('value')->nullable()->comment('設定値');
            $table->string('type', 50)->default('string')->comment('値の型');
            $table->text('description')->nullable()->comment('説明');
            $table->boolean('is_overridden')->default(false)->comment('システム設定上書きフラグ');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('最終更新者');
            $table->timestamps();
            
            $table->unique(['company_id', 'category', 'key']);
            $table->index('company_id');
            $table->index('category');
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};