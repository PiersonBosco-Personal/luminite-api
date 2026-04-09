<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_stacks', function (Blueprint $table) {
            $table->dropColumn('app_label');
            $table->string('version')->nullable()->after('name');
            $table->foreignId('parent_id')
                ->nullable()
                ->after('project_id')
                ->constrained('tech_stacks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tech_stacks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'version']);
            $table->enum('app_label', ['frontend', 'backend', 'mobile', 'other'])->default('other');
        });
    }
};
