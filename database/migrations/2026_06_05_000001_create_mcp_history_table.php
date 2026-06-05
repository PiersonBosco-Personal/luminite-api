<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_token_id')->nullable()->constrained('mcp_tokens')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('tool')->index();
            $table->json('arguments')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('result_summary', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_history');
    }
};
