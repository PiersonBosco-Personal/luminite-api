<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labelables', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->morphs('labelable');

            $table->primary(['label_id', 'labelable_id', 'labelable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labelables');
    }
};
