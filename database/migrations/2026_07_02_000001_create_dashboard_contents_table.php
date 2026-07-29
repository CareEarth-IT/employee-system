<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_contents', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->longText('content_html');
            $table->string('page_url')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_contents');
    }
};
