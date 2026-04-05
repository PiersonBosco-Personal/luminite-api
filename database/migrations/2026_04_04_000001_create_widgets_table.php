<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('category', ['productivity', 'analytics', 'team', 'ai']);
            $table->text('description');
            $table->string('icon');
            $table->json('default_config')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('default_w')->default(4);
            $table->unsignedTinyInteger('default_h')->default(4);
            $table->unsignedTinyInteger('min_w')->default(2);
            $table->unsignedTinyInteger('min_h')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
