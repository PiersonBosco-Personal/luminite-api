<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary_what')->nullable();
            $table->text('summary_why')->nullable();
            $table->enum('source', ['claude', 'human']);
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'created_at']); // latest-per-task
            $table->index('created_at');               // newest-first feed
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_completions');
    }
};
