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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('企業名');
            $table->string('code', 50)->unique()->comment('企業コード');
            $table->string('email', 255)->nullable()->comment('企業代表メール');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('address', 500)->nullable()->comment('住所');
            $table->string('postal_code', 10)->nullable()->comment('郵便番号');
            $table->string('representative_name', 255)->nullable()->comment('代表者名');
            $table->string('slack_workspace_id', 100)->nullable()->comment('Slack ワークスペースID');
            $table->string('slack_workspace_name', 255)->nullable()->comment('Slack ワークスペース名');
            $table->integer('max_users')->default(50)->comment('最大ユーザー数');
            $table->date('contract_start_date')->nullable()->comment('契約開始日');
            $table->date('contract_end_date')->nullable()->comment('契約終了日');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->comment('ステータス');
            $table->json('settings')->nullable()->comment('企業個別設定');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('code');
            $table->index('status');
            $table->index(['contract_start_date', 'contract_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};