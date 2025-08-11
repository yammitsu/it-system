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
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade')->comment('課題ID');
            $table->foreignId('depends_on_task_id')->constrained('tasks')->onDelete('cascade')->comment('依存先課題ID');
            $table->enum('dependency_type', ['required', 'recommended'])->default('required')->comment('依存タイプ');
            $table->text('description')->nullable()->comment('依存関係の説明');
            $table->timestamps();
            
            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index('task_id');
            $table->index('depends_on_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};