<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('affiliation_histories')
            ->where('company', 'GrowTEC')
            ->update(['company' => 'GROWTEC']);
    }

    public function down(): void
    {
        DB::table('affiliation_histories')
            ->where('company', 'GROWTEC')
            ->update(['company' => 'GrowTEC']);
    }
};
