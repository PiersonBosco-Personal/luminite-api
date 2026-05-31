<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->string('color', 32)->nullable()->after('is_active');
        });

        // Backfill the six seeded defaults by name. Any work type that has
        // been renamed away from a default won't match — those keep color
        // null, which the frontend renders as the neutral slate fallback.
        $defaults = [
            'Development'   => 'blue',
            'Testing'       => 'green',
            'Design'        => 'purple',
            'Meeting'       => 'amber',
            'Documentation' => 'cyan',
            'Other'         => 'slate',
        ];

        foreach ($defaults as $name => $color) {
            DB::table('work_types')
                ->where('name', $name)
                ->whereNull('color')
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
