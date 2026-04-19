<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create environment_types table
        Schema::create('environment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('gray');
            $table->unsignedInteger('per_app_limit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Add columns to environments
        Schema::table('environments', function (Blueprint $table) {
            $table->foreignId('environment_type_id')->nullable()->after('app_id')->constrained('environment_types')->cascadeOnDelete();
            $table->string('custom_label')->nullable()->after('color');
        });

        // 3. Backfill: create types from distinct (label, color) pairs
        $pairs = DB::table('environments')
            ->select('label', 'color')
            ->distinct()
            ->get();

        $sortOrder = 0;
        foreach ($pairs as $pair) {
            DB::table('environment_types')->insert([
                'name' => $pair->label,
                'color' => $pair->color,
                'per_app_limit' => null,
                'sort_order' => $sortOrder++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Link existing environments to their types
        $types = DB::table('environment_types')->get();
        foreach ($types as $type) {
            DB::table('environments')
                ->where('label', $type->name)
                ->where('color', $type->color)
                ->whereNull('environment_type_id')
                ->update(['environment_type_id' => $type->id]);
        }

        // 5. Make environment_type_id non-nullable
        Schema::table('environments', function (Blueprint $table) {
            $table->foreignId('environment_type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('environment_type_id');
            $table->dropColumn('custom_label');
        });

        Schema::dropIfExists('environment_types');
    }
};
