<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            // Built + functional. Defaults true so existing rows stay usable;
            // the seeder flips the unbuilt stubs to false.
            $table->boolean('is_available')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->dropColumn('is_available');
        });
    }
};
