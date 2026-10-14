<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_insurance_threshold_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->unsignedInteger('threshold');
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->unique(['company', 'threshold'], 'si_threshold_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_insurance_threshold_notifications');
    }
};
