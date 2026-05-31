<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_type_id')->nullable()->constrained('work_types')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->date('logged_at');
            $table->timestamps();

            $table->index(['project_id', 'logged_at']);
            $table->index(['user_id', 'duration_minutes']);
            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
