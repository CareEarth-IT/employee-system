<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_hr_details')
            ->whereRaw('UPPER(affiliation_code) = ?', ['MD'])
            ->update(['affiliation_code' => 'ME']);
    }

    public function down(): void
    {
        // MD と ME の区別は復元できないため no-op
    }
};
