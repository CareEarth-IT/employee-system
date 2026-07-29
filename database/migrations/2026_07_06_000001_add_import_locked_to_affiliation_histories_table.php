<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->boolean('import_locked')->default(false)->after('job_description');
        });
    }

    public function down(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->dropColumn('import_locked');
        });
    }
};
