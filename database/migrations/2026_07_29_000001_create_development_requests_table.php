<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('request_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('requester_name');
            $table->string('requester_department')->nullable();
            $table->string('requester_number', 64);
            $table->string('requester_email');
            $table->date('request_date');
            $table->string('content_type');
            $table->string('sub_type')->nullable();
            $table->string('title', 30);
            $table->text('purpose');
            $table->text('detail');
            $table->string('progress')->default('相談前');
            $table->text('remarks')->nullable();
            $table->string('estimated_hours', 32)->nullable();
            $table->string('actual_hours', 32)->nullable();
            $table->date('development_target_date')->nullable();
            $table->string('development_assignee')->default('未');
            $table->string('manager')->nullable();
            $table->timestamps();

            $table->index('request_date');
            $table->index('progress');
            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_requests');
    }
};
