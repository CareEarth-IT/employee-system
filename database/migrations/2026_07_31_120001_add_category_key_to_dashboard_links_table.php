<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_links', function (Blueprint $table) {
            $table->string('category_key', 50)->nullable()->after('tab_key');
            $table->index(['tab_key', 'category_key']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_links', function (Blueprint $table) {
            $table->dropIndex(['tab_key', 'category_key']);
            $table->dropColumn('category_key');
        });
    }
};
