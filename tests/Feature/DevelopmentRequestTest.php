<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\DevelopmentRequest;
use App\Models\User;
use App\Services\DevelopmentRequestChatNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DevelopmentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_development_request(): void
    {
        Http::fake();
        config(['development_requests.chat_webhook_url' => 'https://chat.example.test/webhook']);

        $user = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般', '10042');

        $this->actingAs($user)
            ->post(route('development-requests.store'), [
                'request_date' => now()->toDateString(),
                'content_type' => 'Airtable',
                'title' => 'テスト依頼タイトル',
                'purpose' => '改善目的',
                'detail' => '詳細内容です',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('development_requests', [
            'requester_email' => 'applicant@careearth.info',
            'title' => 'テスト依頼タイトル',
            'content_type' => 'Airtable',
            'manager' => 'カデアー',
            'progress' => '相談前',
            'request_number' => 10001,
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://chat.example.test/webhook');
    }

    public function test_store_requires_employee_id(): void
    {
        $user = $this->makeUser('no-id@careearth.info', '大阪営業部', '一般', null);

        $this->actingAs($user)
            ->from(route('development-requests.create'))
            ->post(route('development-requests.store'), [
                'request_date' => now()->toDateString(),
                'content_type' => 'その他',
                'title' => 'タイトル',
                'purpose' => '目的',
                'detail' => '詳細',
            ])
            ->assertRedirect(route('development-requests.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('development_requests', 0);
    }

    public function test_list_is_visible_to_all_authenticated_users(): void
    {
        $viewer = $this->makeUser('viewer@careearth.info', '通信部', '一般', '200');
        $applicant = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般', '100');

        DevelopmentRequest::create([
            'request_number' => 10001,
            'user_id' => $applicant->id,
            'requester_name' => '申請者',
            'requester_department' => '大阪営業部',
            'requester_number' => '100',
            'requester_email' => $applicant->email,
            'request_date' => now()->toDateString(),
            'content_type' => 'Airtable',
            'title' => '一覧に出る依頼',
            'purpose' => '目的',
            'detail' => '詳細',
            'progress' => '相談前',
            'development_assignee' => '未',
            'manager' => 'カデアー',
        ]);

        $this->actingAs($viewer)
            ->get(route('development-requests.index'))
            ->assertOk()
            ->assertSee('一覧に出る依頼', false)
            ->assertDontSee('詳細</a>', false);
    }

    public function test_editor_can_view_and_update_detail(): void
    {
        $editor = $this->makeUser('yuta_masui@careearth.info', '情報システム部', '一般', '1');
        $applicant = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般', '100');

        $request = DevelopmentRequest::create([
            'request_number' => 10055,
            'user_id' => $applicant->id,
            'requester_name' => '申請者',
            'requester_department' => '大阪営業部',
            'requester_number' => '100',
            'requester_email' => $applicant->email,
            'request_date' => now()->toDateString(),
            'content_type' => 'Airtable',
            'title' => '編集対象',
            'purpose' => '目的',
            'detail' => '詳細',
            'progress' => '相談前',
            'development_assignee' => '未',
            'manager' => 'カデアー',
        ]);

        $this->assertTrue($editor->canEditDevelopmentRequest());

        $this->actingAs($editor)
            ->get(route('development-requests.show', $request))
            ->assertOk()
            ->assertSee('編集対象', false);

        $this->actingAs($editor)
            ->put(route('development-requests.update', $request), [
                'progress' => '開発中',
                'remarks' => '着手しました',
                'estimated_hours' => '2.5',
                'actual_hours' => '1',
                'development_target_date' => now()->addWeek()->toDateString(),
                'development_assignee' => '増井',
                'content_type_label' => '派遣以外',
            ])
            ->assertRedirect(route('development-requests.index'));

        $request->refresh();
        $this->assertSame('開発中', $request->progress);
        $this->assertSame('着手しました', $request->remarks);
        $this->assertSame('2.5', $request->estimated_hours);
        $this->assertSame('増井', $request->development_assignee);
        $this->assertSame('派遣以外の開発システム', $request->content_type);
        $this->assertSame('増井', $request->manager);
    }

    public function test_viewer_can_open_detail_but_cannot_update(): void
    {
        $viewer = $this->makeUser('ginga_fukui@careearth.info', '役員', '一般', '9');
        $applicant = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般', '100');

        $request = DevelopmentRequest::create([
            'request_number' => 10056,
            'user_id' => $applicant->id,
            'requester_name' => '申請者',
            'requester_department' => '大阪営業部',
            'requester_number' => '100',
            'requester_email' => $applicant->email,
            'request_date' => now()->toDateString(),
            'content_type' => 'その他',
            'title' => '閲覧のみ',
            'purpose' => '目的',
            'detail' => '詳細',
            'progress' => '相談前',
            'development_assignee' => '未',
            'manager' => '中元',
        ]);

        $this->assertTrue($viewer->canViewDevelopmentRequestDetail());
        $this->assertFalse($viewer->canEditDevelopmentRequest());

        $this->actingAs($viewer)
            ->get(route('development-requests.show', $request))
            ->assertOk()
            ->assertSee('閲覧のみ（編集権限がありません）', false);

        $this->actingAs($viewer)
            ->put(route('development-requests.update', $request), [
                'progress' => '完了',
                'remarks' => '不可',
                'development_assignee' => '未',
                'content_type_label' => 'その他',
            ])
            ->assertForbidden();
    }

    public function test_dashboard_common_tab_includes_development_request_link(): void
    {
        $user = $this->makeUser('anyone@careearth.info', '通信部', '一般', '50');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('開発依頼', false)
            ->assertSee(route('finance-hr.enter', ['category' => 'is']), false);
    }

    public function test_chat_notifier_skips_when_webhook_empty(): void
    {
        Http::fake();
        config(['development_requests.chat_webhook_url' => '']);

        $user = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般', '100');
        $request = DevelopmentRequest::create([
            'request_number' => 10057,
            'user_id' => $user->id,
            'requester_name' => '申請者',
            'requester_department' => '大阪営業部',
            'requester_number' => '100',
            'requester_email' => $user->email,
            'request_date' => now()->toDateString(),
            'content_type' => 'Airtable',
            'title' => '通知なし',
            'purpose' => '目的',
            'detail' => '詳細',
            'progress' => '相談前',
            'development_assignee' => '未',
            'manager' => 'カデアー',
        ]);

        app(DevelopmentRequestChatNotifier::class)->notifySubmitted($request);

        Http::assertNothingSent();
    }

    private function makeUser(string $email, string $department, string $position, ?string $employeeId): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'employee_id' => $employeeId,
            'last_name' => 'テスト',
            'first_name' => '太郎',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'department' => $department,
            'position' => $position,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'location' => '大阪',
        ]);

        return $user->fresh(['affiliationHistories']);
    }
}
