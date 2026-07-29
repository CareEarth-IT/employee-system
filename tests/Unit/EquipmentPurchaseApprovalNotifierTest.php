<?php

namespace Tests\Unit;

use App\Mail\EquipmentPurchaseApprovalRequested;
use App\Mail\EquipmentPurchaseSubmitted;
use App\Models\AffiliationHistory;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use App\Services\EquipmentPurchaseApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EquipmentPurchaseApprovalNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_over_30k_notifies_configured_approver_emails_even_without_matching_user(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '通信部', '一般');
        $application = $this->makeEquipmentApplication($applicant, EquipmentPurchaseApplication::TYPE_INTERNAL_OVER_30K, 35000);

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseSubmitted::class, 1);
        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, 1);
        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('takuya_nishi@careearth.info');
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('nishi@careearth.info');
        });
    }

    public function test_information_systems_notifies_designated_email_not_representative_position(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '情報システム部', '一般');
        $formerRepresentative = $this->makeUser('rep@careearth.info', '代表', '代表');
        $this->makeUser('mariko_nakamoto@careearth.info', '情報システム部', '一般');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            10000,
            department: '情報システム部',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, 1);
        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('mariko_nakamoto@careearth.info');
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($formerRepresentative) {
            return $mail->hasTo($formerRepresentative->email);
        });

        $this->assertTrue($application->fresh()->belongsToInformationSystemsDepartment());
        $approver = User::where('email', 'mariko_nakamoto@careearth.info')->firstOrFail();
        $this->assertTrue($approver->canApproveEquipmentPurchase($application));
        $this->assertFalse($formerRepresentative->canApproveEquipmentPurchase($application));
    }

    public function test_global_manager_approver_can_approve_all_superior_applications_without_mail(): void
    {
        Mail::fake();

        $global = $this->makeUser('employee@gmail.com', '管理本部', '全部署承認');
        $applicant = $this->makeUser('applicant@careearth.info', '通信部', '一般');

        $managerApp = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '不動産部',
        );
        $itApp = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            5000,
            department: '情報システム部',
        );
        $gaApp = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            5000,
            department: '通信部',
        );

        $this->assertTrue($managerApp->requiresSuperiorApproval());
        $this->assertTrue($itApp->requiresSuperiorApproval());
        $this->assertFalse($gaApp->requiresSuperiorApproval());
        $this->assertTrue($global->canApproveEquipmentPurchase($managerApp));
        $this->assertTrue($global->canApproveEquipmentPurchase($itApp));
        $this->assertFalse($global->canApproveEquipmentPurchase($gaApp));

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($managerApp);

        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('employee@gmail.com');
        });
    }

    public function test_onsite_over_30k_notifies_department_manager(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '不動産部', '一般');
        $manager = $this->makeUser('manager@careearth.info', '不動産部', '部長');
        $otherManager = $this->makeUser('other-manager@careearth.info', '通信部', '部長');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '不動産部',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($manager) {
            return $mail->hasTo($manager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($otherManager) {
            return $mail->hasTo($otherManager->email);
        });
    }

    public function test_internal_under_30k_notifies_general_affairs(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '通信部', '一般');
        $generalAffairs = $this->makeUser('ga@careearth.info', '経理部', '一般', '総務課');
        $application = $this->makeEquipmentApplication($applicant, EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K, 12000);

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($generalAffairs) {
            return $mail->hasTo($generalAffairs->email);
        });
    }

    public function test_onsite_over_30k_notifies_branch_manager_for_fukuoka_department(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '福岡営業部', '一般', null, '福岡');
        $branchManager = $this->makeUser('branch-manager@careearth.info', '福岡支店', '支店長', null, '福岡');
        $otherBranchManager = $this->makeUser('other-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '福岡営業部',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($otherBranchManager) {
            return $mail->hasTo($otherBranchManager->email);
        });
    }

    public function test_onsite_over_30k_notifies_branch_manager_for_matching_branch_department(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '福岡営業部', '一般', null, '福岡');
        $branchManager = $this->makeUser('branch-manager@careearth.info', '福岡支店', '支店長', null, '福岡');
        $otherBranchManager = $this->makeUser('other-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '福岡支店',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($otherBranchManager) {
            return $mail->hasTo($otherBranchManager->email);
        });
    }

    public function test_branch_manager_is_department_approver(): void
    {
        $branchManager = $this->makeUser('branch-manager@careearth.info', '福岡支店', '支店長', null, '福岡');
        $leader = $this->makeUser('leader@careearth.info', '福岡営業部', 'リーダー', null, '福岡');

        $this->assertTrue($branchManager->isBranchManager());
        $this->assertTrue($branchManager->canActAsDepartmentApprover());
        $this->assertFalse($leader->canActAsDepartmentApprover());
    }

    public function test_branch_manager_only_locations_exclude_department_manager(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '名古屋営業部', '一般', null, '名古屋');
        $departmentManager = $this->makeUser('nagoya-manager@careearth.info', '名古屋営業部', '部長', null, '名古屋');
        $branchManager = $this->makeUser('nagoya-branch@careearth.info', '名古屋支店', '支店長', null, '名古屋');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '名古屋営業部',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
    }

    public function test_fukuoka_department_manager_does_not_receive_approval_mail(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '福岡営業部', '一般', null, '福岡');
        $departmentManager = $this->makeUser('fukuoka-manager@careearth.info', '福岡営業部', '部長', null, '福岡');
        $branchManager = $this->makeUser('branch-manager@careearth.info', '福岡支店', '支店長', null, '福岡');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '福岡営業部',
        );

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
    }

    public function test_designated_internal_approver_matches_default_email_list(): void
    {
        $takuya = $this->makeUser('takuya_nishi@careearth.info', '経理部', '課長代理', '総務課');
        $other = $this->makeUser('other@careearth.info', '経理部', '課長代理', '総務課');
        $nishi = $this->makeUser('nishi@careearth.info', '経理部', '課長代理', '総務課');

        $this->assertTrue($takuya->isDesignatedInternalOver30kApprover());
        $this->assertFalse($nishi->isDesignatedInternalOver30kApprover());
        $this->assertFalse($other->isDesignatedInternalOver30kApprover());
    }

    public function test_tokyo_over_30k_notifies_department_manager_only_on_submit(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '東京営業部', '一般', null, '東京');
        $departmentManager = $this->makeUser('tokyo-manager@careearth.info', '東京営業部', '部長', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京営業部',
        );

        $this->assertTrue($application->requiresDualApproval());

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
    }

    public function test_tokyo_dual_approval_second_stage_notifies_branch_manager(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '東京営業部', '一般', null, '東京');
        $departmentManager = $this->makeUser('tokyo-manager@careearth.info', '東京営業部', '部長', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京営業部',
        );

        $application->update([
            'first_approved_at' => now(),
            'first_approver_id' => $departmentManager->id,
            'first_approval_decision' => EquipmentPurchaseApplication::DECISION_APPROVED,
        ]);

        app(EquipmentPurchaseApprovalNotifier::class)->notifySecondStage($application->fresh());

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
    }

    public function test_tokyo_branch_manager_cannot_approve_before_first_approval(): void
    {
        $applicant = $this->makeUser('applicant@careearth.info', '東京営業部', '一般', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京営業部',
        );

        $this->assertFalse($branchManager->canApproveEquipmentPurchase($application));
    }

    public function test_tokyo_department_manager_cannot_approve_after_first_approval(): void
    {
        $applicant = $this->makeUser('applicant@careearth.info', '東京営業部', '一般', null, '東京');
        $departmentManager = $this->makeUser('tokyo-manager@careearth.info', '東京営業部', '部長', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京営業部',
        );

        $application->update([
            'first_approved_at' => now(),
            'first_approver_id' => $departmentManager->id,
            'first_approval_decision' => EquipmentPurchaseApplication::DECISION_APPROVED,
        ]);

        $application = $application->fresh();

        $this->assertFalse($departmentManager->canApproveEquipmentPurchase($application));
        $this->assertTrue($branchManager->canApproveEquipmentPurchase($application));
    }

    public function test_tokyo_admin_department_requires_dual_approval_and_notifies_manager(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '東京管理部', '一般', '業務課', '東京');
        $departmentManager = $this->makeUser('tokyo-admin-manager@careearth.info', '大阪管理部,東京管理部', '部長', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京管理部',
        );

        $this->assertTrue($application->requiresDualApproval());

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
    }

    public function test_tokyo_gr_department_requires_dual_approval_and_notifies_manager(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('applicant@careearth.info', '東京グローバル事業部', '一般', null, '東京');
        $departmentManager = $this->makeUser('tokyo-gr-manager@careearth.info', '東京グローバル事業部', '部長', null, '東京');
        $branchManager = $this->makeUser('tokyo-branch@careearth.info', '東京支店', '支店長', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京グローバル事業部',
        );

        $this->assertTrue($application->requiresDualApproval());

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($departmentManager) {
            return $mail->hasTo($departmentManager->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($branchManager) {
            return $mail->hasTo($branchManager->email);
        });
    }

    public function test_tokyo_ss_section_requires_dual_approval(): void
    {
        $applicant = $this->makeUser('applicant@careearth.info', '東京営業部', '一般', 'SS課', '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '東京営業部',
            section: 'SS課',
        );

        $this->assertTrue($application->requiresDualApproval());
    }

    public function test_tokyo_hr_department_does_not_require_dual_approval(): void
    {
        $applicant = $this->makeUser('applicant@careearth.info', '人事部', '一般', null, '東京');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '人事部',
            deliveryDestination: 'tokyo_7F',
        );

        $this->assertTrue($application->isTokyoRelated());
        $this->assertFalse($application->matchesDualApprovalDepartmentKeywords());
        $this->assertFalse($application->requiresDualApproval());
    }

    public function test_food_momotani_under_30k_goes_to_general_affairs(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $ga = $this->makeUser('ga@careearth.info', '経理部', '一般', '総務課');
        $tien = $this->makeUser('nguyenphuong_tien@careearth.info', '食品事業部', '一般', '店舗運営課');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            20000,
            department: '食品事業部',
            deliveryDestination: 'mart_momotani',
        );

        $this->assertSame('momotani', $application->foodApprovalRoute());
        $this->assertFalse($application->requiresFoodDesignatedApprover());
        $this->assertTrue($application->requiresGeneralAffairsApproval());
        $this->assertTrue($ga->canApproveEquipmentPurchase($application));
        $this->assertFalse($tien->canApproveEquipmentPurchase($application));

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($ga) {
            return $mail->hasTo($ga->email);
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($tien) {
            return $mail->hasTo($tien->email);
        });
    }

    public function test_food_momotani_over_30k_notifies_tien(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $manager = $this->makeUser('manager@careearth.info', '食品事業部', '部長');
        $this->makeUser('nguyenphuong_tien@careearth.info', '食品事業部', '一般', '店舗運営課');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            35000,
            department: '食品事業部',
            deliveryDestination: 'mart_momotani',
        );

        $this->assertSame('momotani', $application->foodApprovalRoute());
        $this->assertTrue($application->requiresFoodDesignatedApprover());
        $this->assertFalse($application->requiresManagerApproval());

        $tien = User::where('email', 'nguyenphuong_tien@careearth.info')->firstOrFail();
        $this->assertTrue($tien->canApproveEquipmentPurchase($application));
        $this->assertFalse($manager->canApproveEquipmentPurchase($application));

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('nguyenphuong_tien@careearth.info');
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($manager) {
            return $mail->hasTo($manager->email);
        });
    }

    public function test_food_emergency_under_30k_notifies_sugiura(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $ga = $this->makeUser('ga@careearth.info', '経理部', '一般', '総務課');
        $this->makeUser('kanji_sugiura@careearth.info', '食品事業部', '次長', '食品-総務課');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_PURCHASED_UNDER_10K,
            15000,
            department: '食品事業部',
        );

        $this->assertSame('emergency', $application->foodApprovalRoute());
        $this->assertTrue($application->requiresFoodDesignatedApprover());
        $this->assertFalse($application->requiresGeneralAffairsApproval());
        $this->assertFalse($ga->canApproveEquipmentPurchase($application));

        $sugiura = User::where('email', 'kanji_sugiura@careearth.info')->firstOrFail();
        $this->assertTrue($sugiura->canApproveEquipmentPurchase($application));

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('kanji_sugiura@careearth.info');
        });
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) use ($ga) {
            return $mail->hasTo($ga->email);
        });
    }

    public function test_food_emergency_over_30k_notifies_thinh(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $this->makeUser('buicuongthinh@careearth.info', '大阪グローバル事業部', '執行役員');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_PURCHASED_OVER_10K,
            40000,
            department: '食品事業部',
        );

        $this->assertSame('emergency', $application->foodApprovalRoute());
        $thinh = User::where('email', 'buicuongthinh@careearth.info')->firstOrFail();
        $this->assertTrue($thinh->canApproveEquipmentPurchase($application));

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('buicuongthinh@careearth.info');
        });
    }

    public function test_food_logistics_over_30k_notifies_sugiura(): void
    {
        Mail::fake();

        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $this->makeUser('kanji_sugiura@careearth.info', '食品事業部', '次長', '食品-総務課');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '食品事業部',
            deliveryDestination: 'mart_souko',
        );

        $this->assertSame('logistics', $application->foodApprovalRoute());
        $this->assertTrue($application->requiresFoodDesignatedApprover());

        app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);

        Mail::assertSent(EquipmentPurchaseApprovalRequested::class, function (EquipmentPurchaseApprovalRequested $mail) {
            return $mail->hasTo('kanji_sugiura@careearth.info');
        });
    }

    public function test_food_logistics_under_30k_goes_to_general_affairs(): void
    {
        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $ga = $this->makeUser('ga@careearth.info', '経理部', '一般', '総務課');

        $application = $this->makeEquipmentApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_UNDER_30K,
            12000,
            department: '食品事業部',
            deliveryDestination: 'mart_souko',
        );

        $this->assertSame('logistics', $application->foodApprovalRoute());
        $this->assertFalse($application->requiresFoodDesignatedApprover());
        $this->assertTrue($ga->canApproveEquipmentPurchase($application));
    }

    private function makeUser(
        string $email,
        string $department,
        string $position,
        ?string $section = null,
        string $location = '大阪',
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'department' => $department,
            'section' => $section,
            'position' => $position,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'location' => $location,
        ]);

        return $user->fresh(['affiliationHistories']);
    }

    private function makeEquipmentApplication(
        User $user,
        string $type,
        int $price,
        ?string $department = null,
        ?string $section = null,
        string $deliveryDestination = 'osaka_2f',
    ): EquipmentPurchaseApplication {
        return EquipmentPurchaseApplication::create([
            'user_id' => $user->id,
            'application_type' => $type,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/item',
            'product_name' => 'テスト備品',
            'quantity' => 1,
            'price_including_tax' => $price,
            'purchase_reason' => 'テスト',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'department' => $department ?? $user->currentDepartment(),
            'section' => $section,
            'delivery_destination' => $deliveryDestination,
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => now()->toDateString(),
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
        ]);
    }
}
