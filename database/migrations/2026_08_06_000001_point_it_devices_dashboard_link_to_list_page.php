<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_links')) {
            return;
        }

        DB::table('dashboard_links')
            ->where('label', '情シスデバイス用')
            ->update([
                'url' => '/it-devices',
                'visibility_rule' => 'it_device_list',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 手動で設定された URL を復元できないため no-op
    }
};
