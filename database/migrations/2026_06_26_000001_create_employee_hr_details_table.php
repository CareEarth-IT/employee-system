<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_hr_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('name_kana_fullwidth')->nullable();
            $table->string('name_kana_halfwidth')->nullable();

            $table->string('affiliation_code')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('employment_status')->nullable();
            $table->date('resigned_at')->nullable();
            $table->date('last_working_day')->nullable();

            $table->string('residence_status')->nullable();
            $table->date('residence_expires_at')->nullable();
            $table->text('residence_renewal_memo')->nullable();
            $table->string('residence_card_renewal_status')->nullable();

            $table->string('department_primary')->nullable();
            $table->string('section_primary')->nullable();
            $table->string('position_primary')->nullable();
            $table->string('department_secondary')->nullable();
            $table->string('section_secondary')->nullable();
            $table->string('position_secondary')->nullable();
            $table->string('jurisdiction')->nullable();

            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('personal_email')->nullable();
            $table->boolean('my_number_verified')->default(false);
            $table->text('remarks')->nullable();

            $table->text('address_as_of_jan1')->nullable();
            $table->string('previous_withholding_slip')->nullable();
            $table->string('resident_tax_switch_form')->nullable();

            $table->string('money_forward_setup')->nullable();
            $table->string('rakuraku_seisan_setup')->nullable();
            $table->string('smarthr_setup')->nullable();
            $table->string('business_card_onboarding')->nullable();
            $table->date('health_check_received_at')->nullable();

            $table->string('employment_insurance_number')->nullable();
            $table->date('employment_insurance_applied_at')->nullable();
            $table->string('health_pension_number')->nullable();
            $table->date('health_pension_applied_at')->nullable();
            $table->string('dependent_add_social_insurance')->nullable();

            $table->boolean('has_pc')->default(false);
            $table->string('pc_manufacturer')->nullable();
            $table->boolean('has_mobile')->default(false);
            $table->string('mobile_manufacturer')->nullable();
            $table->boolean('setup_completed')->default(false);
            $table->boolean('device_collected')->default(false);
            $table->boolean('microsoft_account_removed')->default(false);
            $table->boolean('gws_account_removed')->default(false);
            $table->boolean('slack_account_removed')->default(false);

            $table->string('resident_tax_transfer_form')->nullable();
            $table->string('employment_insurance_withdrawal')->nullable();
            $table->date('employment_insurance_withdrawal_applied_at')->nullable();
            $table->string('health_pension_withdrawal')->nullable();
            $table->date('health_pension_withdrawal_applied_at')->nullable();
            $table->string('withholding_tax_slip')->nullable();
            $table->string('separation_certificate')->nullable();
            $table->string('resignation_certificate')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_hr_details');
    }
};
