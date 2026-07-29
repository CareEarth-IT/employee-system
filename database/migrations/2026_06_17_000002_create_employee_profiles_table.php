<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('english_name')->nullable();
            $table->string('name_kana')->nullable();
            $table->string('abbreviated_name', 10)->nullable();
            $table->date('joined_at')->nullable();
            $table->string('nationality')->nullable();
            $table->text('languages')->nullable();
            $table->text('self_introduction')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
