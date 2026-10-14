<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->index('employment_status', 'employee_hr_details_employment_status_index');
        });

        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->index(
                ['user_id', 'enrollment_status', 'end_date', 'start_date'],
                'affiliation_histories_current_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->dropIndex('employee_hr_details_employment_status_index');
        });

        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->dropIndex('affiliation_histories_current_lookup_index');
        });
    }
};
