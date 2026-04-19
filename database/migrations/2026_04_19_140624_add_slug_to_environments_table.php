<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('custom_label');
        });

        // Backfill: slug from custom_label or type name, disambiguated per app.
        $envs = DB::table('environments')
            ->leftJoin('environment_types', 'environments.environment_type_id', '=', 'environment_types.id')
            ->orderBy('environments.app_id')
            ->orderBy('environments.id')
            ->get([
                'environments.id',
                'environments.app_id',
                'environments.custom_label',
                'environment_types.name as type_name',
            ]);

        $takenByApp = [];
        foreach ($envs as $env) {
            $base = Str::slug($env->custom_label ?: ($env->type_name ?: 'default'));
            $base = $base !== '' ? $base : 'default';

            $slug = $base;
            $counter = 2;
            while (!empty($takenByApp[$env->app_id][$slug])) {
                $slug = $base . '-' . $counter++;
            }
            $takenByApp[$env->app_id][$slug] = true;

            DB::table('environments')->where('id', $env->id)->update(['slug' => $slug]);
        }

        Schema::table('environments', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['app_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropUnique(['app_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
