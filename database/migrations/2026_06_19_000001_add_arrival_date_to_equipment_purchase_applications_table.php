<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->date('arrival_date')->nullable()->after('order_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->dropColumn('arrival_date');
        });
    }
};
