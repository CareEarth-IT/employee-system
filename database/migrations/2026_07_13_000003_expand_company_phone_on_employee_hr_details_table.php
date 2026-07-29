<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employee_hr_details MODIFY company_phone VARCHAR(255) NULL');

            return;
        }

        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('company_phone', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employee_hr_details MODIFY company_phone VARCHAR(50) NULL');

            return;
        }

        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('company_phone', 50)->nullable()->change();
        });
    }
};
