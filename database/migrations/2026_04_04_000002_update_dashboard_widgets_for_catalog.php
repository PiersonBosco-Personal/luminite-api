<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            // Remove old single-table type column (config stays — now used for per-instance overrides)
            $table->dropColumn('type');

            // Add two-table architecture columns
            $table->foreignId('user_id')->after('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->after('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['widget_id']);
            $table->dropColumn(['user_id', 'widget_id']);

            $table->string('type')->after('project_id');
        });
    }
};
