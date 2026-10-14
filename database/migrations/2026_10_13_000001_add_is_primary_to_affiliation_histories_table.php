<?php

use App\Models\AffiliationHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('enrollment_status');
            $table->index(['user_id', 'is_primary']);
        });

        $today = now()->toDateString();

        $userIds = DB::table('affiliation_histories')
            ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $primaryId = DB::table('affiliation_histories')
                ->where('user_id', $userId)
                ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
                })
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->value('id');

            if ($primaryId) {
                DB::table('affiliation_histories')->where('id', $primaryId)->update(['is_primary' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('affiliation_histories', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
