<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->string('gmail_address')->nullable()->after('personal_email');
        });
    }

    public function down(): void
    {
        Schema::table('employee_hr_details', function (Blueprint $table) {
            $table->dropColumn('gmail_address');
        });
    }
};
