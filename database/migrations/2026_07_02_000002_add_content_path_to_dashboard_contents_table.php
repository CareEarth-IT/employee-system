<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_contents', function (Blueprint $table) {
            $table->string('content_path')->nullable()->after('content_html');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_contents', function (Blueprint $table) {
            $table->dropColumn('content_path');
        });
    }
};
