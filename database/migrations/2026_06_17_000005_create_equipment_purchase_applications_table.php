<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_purchase_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('application_type', 50);
            $table->date('application_date');
            $table->string('purchase_site', 50);
            $table->string('purchase_site_other')->nullable();
            $table->string('purchase_site_url', 2000);
            $table->string('product_name');
            $table->string('size')->nullable();
            $table->string('color_model')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price_including_tax');
            $table->text('remarks')->nullable();
            $table->text('purchase_reason');
            $table->string('item_destination', 50);
            $table->string('department')->nullable();
            $table->string('section')->nullable();
            $table->string('delivery_destination', 50);
            $table->string('delivery_zip', 10)->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_recipient_name')->nullable();
            $table->string('delivery_recipient_phone', 30)->nullable();
            $table->string('purchase_urgency', 50);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_purchase_applications');
    }
};
