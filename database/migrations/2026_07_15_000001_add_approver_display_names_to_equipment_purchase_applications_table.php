<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->string('approver_display_name')->nullable()->after('approver_id');
            $table->string('first_approver_display_name')->nullable()->after('first_approver_id');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->dropColumn(['approver_display_name', 'first_approver_display_name']);
        });
    }
};
