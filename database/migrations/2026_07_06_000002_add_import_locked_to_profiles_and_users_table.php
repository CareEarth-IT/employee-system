<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->boolean('import_locked')->default(false)->after('photo_path');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('import_locked')->default(false)->after('role');
        });

        DB::table('employee_profiles')->update(['import_locked' => true]);
        DB::table('users')->update(['import_locked' => true]);
        DB::table('affiliation_histories')->update(['import_locked' => true]);
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('import_locked');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('import_locked');
        });
    }
};
