<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->date('order_date')->nullable()->after('application_date');
            $table->boolean('receipt_issued')->default(false)->after('order_date');
            $table->foreignId('orderer_id')->nullable()->after('receipt_issued')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orderer_id');
            $table->dropColumn(['order_date', 'receipt_issued']);
        });
    }
};
