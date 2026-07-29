<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('company_phone', 50)->nullable()->after('slack_account_removed');
        });
    }

    public function down(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->dropColumn('company_phone');
        });
    }
};
