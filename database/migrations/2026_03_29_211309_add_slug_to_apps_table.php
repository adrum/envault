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
        Schema::table('apps', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Backfill slugs from existing app names
        $apps = DB::table('apps')->whereNull('deleted_at')->get();
        foreach ($apps as $app) {
            $baseSlug = Str::slug($app->name);
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('apps')->where('slug', $slug)->where('id', '!=', $app->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            DB::table('apps')->where('id', $app->id)->update(['slug' => $slug]);
        }

        Schema::table('apps', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
