<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_affiliation_records', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('last_name_sort')->nullable();
            $table->string('employee_id', 32)->nullable();
            $table->string('location')->nullable();
            $table->string('department')->nullable();
            $table->string('section')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['year_month', 'user_id']);
            $table->index('year_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_affiliation_records');
    }
};
