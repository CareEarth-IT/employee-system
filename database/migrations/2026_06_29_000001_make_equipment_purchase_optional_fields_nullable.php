<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->string('purchase_site_url', 2000)->nullable()->change();
            $table->string('delivery_destination', 50)->nullable()->change();
            $table->string('purchase_urgency', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->string('purchase_site_url', 2000)->nullable(false)->change();
            $table->string('delivery_destination', 50)->nullable(false)->change();
            $table->string('purchase_urgency', 50)->nullable(false)->change();
        });
    }
};
