<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->constrained('attachment_folders')->nullOnDelete()->after('size');
            $table->string('mime_type')->after('url');
            $table->string('path')->after('mime_type');
            $table->string('disk')->default('local')->after('path');
            $table->string('original_name')->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['mime_type', 'path', 'disk', 'original_name']);
        });
    }
};
