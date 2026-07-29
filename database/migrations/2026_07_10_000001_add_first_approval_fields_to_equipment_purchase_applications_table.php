<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->timestamp('first_approved_at')->nullable()->after('approval_memo');
            $table->foreignId('first_approver_id')->nullable()->after('first_approved_at')->constrained('users')->nullOnDelete();
            $table->string('first_approval_decision', 20)->nullable()->after('first_approver_id');
            $table->text('first_approval_memo')->nullable()->after('first_approval_decision');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_purchase_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_approver_id');
            $table->dropColumn([
                'first_approved_at',
                'first_approval_decision',
                'first_approval_memo',
            ]);
        });
    }
};
