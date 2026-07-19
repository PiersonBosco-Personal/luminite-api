<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('decision');
            $table->text('rationale');
            $table->string('status')->default('active');
            // Self-referencing FK: what replaced this decision. nullOnDelete so
            // deleting a successor unlinks rather than cascade-deleting history.
            $table->foreignId('superseded_by_id')->nullable()->constrained('decisions')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status']); // the recall query path
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
