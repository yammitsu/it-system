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
        // 企業ライセンス管理テーブル
        Schema::create('company_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade')->comment('企業ID');
            $table->string('license_type', 100)->comment('ライセンスタイプ');
            $table->integer('total_seats')->comment('総シート数');
            $table->integer('used_seats')->default(0)->comment('使用済みシート数');
            $table->date('valid_from')->comment('有効開始日');
            $table->date('valid_until')->comment('有効終了日');
            $table->decimal('price', 10, 2)->nullable()->comment('価格');
            $table->string('currency', 3)->default('JPY')->comment('通貨');
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active')->comment('ステータス');
            $table->json('features')->nullable()->comment('機能制限');
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('license_type');
            $table->index('status');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_licenses');
    }
};