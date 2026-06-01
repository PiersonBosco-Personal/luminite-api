<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('widgets')->whereIn('slug', ['tasks_list', 'tech_stack', 'team_presence'])->delete();
    }

    public function down(): void
    {
        // Intentionally empty — these widgets are not coming back.
    }
};
