<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_link_categories', function (Blueprint $table) {
            $table->id();
            $table->string('tab_key', 50);
            $table->string('category_key', 50);
            $table->string('label', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tab_key', 'category_key']);
            $table->index(['tab_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_link_categories');
    }
};
