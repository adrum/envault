<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variable_versions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('variable_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('variable_versions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
