<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_links', function (Blueprint $table) {
            $table->id();
            $table->string('tab_key', 50);
            $table->string('label');
            $table->string('url', 2048)->nullable();
            $table->string('kind', 20)->default('link');
            $table->string('action_route', 100)->nullable();
            $table->string('modal_target', 100)->nullable();
            $table->string('visibility_rule', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tab_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_links');
    }
};
