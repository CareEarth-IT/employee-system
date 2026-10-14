<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_affiliation_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date');
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->string('department')->nullable();
            $table->string('section')->nullable();
            $table->string('position')->nullable();
            $table->text('job_description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('applied_affiliation_id')->nullable()->constrained('affiliation_histories')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_affiliation_changes');
    }
};
