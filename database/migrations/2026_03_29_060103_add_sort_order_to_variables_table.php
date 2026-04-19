<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('key');
        });

        // Backfill existing rows: assign sort_order based on current id order per app
        DB::table('variables')
            ->orderBy('app_id')
            ->orderBy('id')
            ->eachById(function ($variable) {
                static $counters = [];
                $appId = $variable->app_id;

                if (!isset($counters[$appId])) {
                    $counters[$appId] = 0;
                }

                DB::table('variables')
                    ->where('id', $variable->id)
                    ->update(['sort_order' => $counters[$appId]++]);
            });
    }

    public function down(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
