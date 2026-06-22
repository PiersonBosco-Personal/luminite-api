<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
        });

        // The magic-link flow is removed — drop both token indexes then the column.
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropIndex(['token']);
            $table->dropColumn('token');
        });
    }

    public function down(): void
    {
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->dropColumn('declined_at');
        });

        Schema::table('project_invitations', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('email');
            $table->index(['token']);
        });
    }
};
