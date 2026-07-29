<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('primary_id', 20)->nullable()->unique()->after('phone');
        });

        DB::table('employee_hr_details')
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->each(function ($row) {
                DB::table('employee_hr_details')
                    ->where('id', $row->id)
                    ->update(['primary_id' => sprintf('P%06d', $row->user_id)]);
            });
    }

    public function down(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->dropUnique(['primary_id']);
            $table->dropColumn('primary_id');
        });
    }
};
