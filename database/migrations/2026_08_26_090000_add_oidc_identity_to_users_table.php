<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oidc_provider', 64)->nullable()->after('email');
            $table->string('oidc_sub', 255)->nullable()->after('oidc_provider');

            // Without deleted_at, matching users_email_unique. Including a
            // nullable column would make the index inert for live rows, since
            // MySQL treats NULLs as distinct and every live row has deleted_at
            // NULL, so duplicate identities would be accepted.
            $table->unique(['oidc_provider', 'oidc_sub']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['oidc_provider', 'oidc_sub']);
            $table->dropColumn(['oidc_provider', 'oidc_sub']);
        });
    }
};
