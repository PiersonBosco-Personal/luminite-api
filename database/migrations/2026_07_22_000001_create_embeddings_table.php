<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');            // 'decision' | 'thread_entry'
            $table->unsignedBigInteger('source_id');
            $table->string('content_hash');           // sha256 of the embedded text — skip re-embedding unchanged text
            $table->timestamps();

            $table->unique(['source_type', 'source_id']); // one embedding per source row
            $table->index('project_id');                  // the recall scan path
        });

        // The vector column is a Postgres-only feature (pgvector). Under SQLite
        // (the test suite) it is simply absent; every code path guards on the driver.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE embeddings ADD COLUMN embedding vector(1536)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
