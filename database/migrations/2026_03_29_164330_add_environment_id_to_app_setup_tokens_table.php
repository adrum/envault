<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_setup_tokens', function (Blueprint $table) {
            $table->foreignId('environment_id')->nullable()->after('app_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_setup_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('environment_id');
        });
    }
};
