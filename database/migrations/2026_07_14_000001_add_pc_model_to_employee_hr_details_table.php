<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('pc_model', 100)->nullable()->after('pc_manufacturer');
        });
    }

    public function down(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->dropColumn('pc_model');
        });
    }
};
