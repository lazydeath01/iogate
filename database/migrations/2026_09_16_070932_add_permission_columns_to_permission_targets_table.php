<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->json('permission')->nullable()->after('is_active');
        });

        Schema::table('person_types', function (Blueprint $table): void {
            $table->json('permission')->nullable()->after('permission_id');
        });

        Schema::table('persons', function (Blueprint $table): void {
            $table->json('permission')->nullable()->after('special_permission');
        });

        DB::table('person_types')
            ->join('permissions', 'permissions.id', '=', 'person_types.permission_id')
            ->whereNotNull('person_types.permission_id')
            ->update(['person_types.permission' => DB::raw('permissions.constraints')]);

        DB::table('persons')
            ->whereNotNull('special_permission')
            ->update(['permission' => DB::raw('special_permission')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('permission');
        });

        Schema::table('person_types', function (Blueprint $table): void {
            $table->dropColumn('permission');
        });

        Schema::table('persons', function (Blueprint $table): void {
            $table->dropColumn('permission');
        });
    }
};
