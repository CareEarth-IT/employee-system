<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->string('enrollment_status')->default('在籍中')->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->dropColumn('enrollment_status');
        });
    }
};
