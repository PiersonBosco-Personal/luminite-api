<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);     // e.g. 'task.completed', 'section.renamed'
            $table->string('subject_type', 30);   // 'task', 'section', 'label', 'tech_stack'
            $table->unsignedBigInteger('subject_id')->nullable(); // null after deletion
            $table->string('subject_label', 255); // display name captured at log time
            $table->string('description', 500);   // pre-rendered human-readable sentence
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->string('field_changed', 50)->nullable(); // 'due_date', 'assigned_to', 'status', 'name', 'version'
            $table->boolean('via_mcp')->default(false);
            $table->string('debounce_key', 120)->nullable()->index(); // '{userId}:{eventType}:{subjectId}:{fieldChanged}'
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
