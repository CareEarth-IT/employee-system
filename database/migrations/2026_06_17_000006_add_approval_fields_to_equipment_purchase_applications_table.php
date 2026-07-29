<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approver_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('approval_decision', 20)->nullable()->after('approver_id');
            $table->text('approval_memo')->nullable()->after('approval_decision');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn(['approved_at', 'approval_decision', 'approval_memo']);
        });
    }
};
