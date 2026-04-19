<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('color')->default('gray');
            $table->timestamps();
        });

        // Add environment_id to variables
        Schema::table('variables', function (Blueprint $table) {
            $table->foreignId('environment_id')->nullable()->after('app_id')->constrained()->cascadeOnDelete();
        });

        // Create a "Default" environment for every existing app and assign variables to it
        $apps = DB::table('apps')->pluck('id');

        foreach ($apps as $appId) {
            $envId = DB::table('environments')->insertGetId([
                'app_id' => $appId,
                'label' => 'Default',
                'color' => 'gray',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('variables')
                ->where('app_id', $appId)
                ->whereNull('environment_id')
                ->update(['environment_id' => $envId]);
        }

        // Now make environment_id non-nullable
        Schema::table('variables', function (Blueprint $table) {
            $table->foreignId('environment_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('variables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('environment_id');
        });

        Schema::dropIfExists('environments');
    }
};
