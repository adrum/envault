<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            $table->string('comment_prefix')->nullable()->after('is_commented');
        });
    }

    public function down(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            $table->dropColumn('comment_prefix');
        });
    }
};
