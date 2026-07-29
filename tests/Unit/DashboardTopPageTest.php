<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\DashboardContent;
use App\Models\DashboardLink;
use App\Models\User;
use App\Support\DashboardTab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_tab_is_visible_to_all_users(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->assertTrue(DashboardTab::canViewTab($user, 'common'));
    }

    public function test_all_department_tabs_are_always_visible_on_dashboard(): void
    {
        $user = $this->userInDepartment('不動産部');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        foreach (['社員共通', '派遣事業', '特定技能', '不動産', '食品', '通信', '美容', '社用車'] as $label) {
            $response->assertSee($label, false);
        }
    }

    public function test_company_car_tab_shows_drive_sync_links(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'company-car']))
            ->assertOk()
            ->assertSee('社用車の初めて使用する方はこちら', false)
            ->assertSee('部署が変更された方はこちら', false)
            ->assertSee(route('drive-app.sync'), false);
    }

    public function test_dashboard_shows_html_announcement(): void
    {
        Storage::fake('public');

        $user = $this->userInDepartment('通信部');

        DashboardContent::persistHtml('通信', '<p><strong>重要</strong>なお知らせ</p>', $user->id);

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'telecom']))
            ->assertOk()
            ->assertSee('重要', false)
            ->assertSee('なお知らせ', false);
    }

    public function test_user_can_create_announcement_on_dedicated_screen(): void
    {
        Storage::fake('public');

        $user = $this->informationSystemsUser();

        $this->actingAs($user)
            ->get(route('dashboard.announcements.create', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('お知らせ作成', false)
            ->assertSee('お知らせ', false);

        $this->actingAs($user)
            ->post(route('dashboard.announcements.store'), [
                'department' => '社員共通',
                'content_html' => '<p>全社向け</p>',
                'tab' => 'common',
                'is_visible' => '1',
            ])
            ->assertRedirect(route('dashboard', ['tab' => 'common']));

        $this->assertDatabaseHas('dashboard_contents', [
            'department' => '社員共通',
            'content_html' => '<p>全社向け</p>',
        ]);

        Storage::disk('public')->assertExists('dashboard/contents/common/1.html');
    }

    public function test_dashboard_shows_create_announcement_link_only_for_authorized_editors(): void
    {
        $employee = $this->userInDepartment('通信部');
        $editor = $this->informationSystemsUser();

        $this->actingAs($employee)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertDontSee('お知らせを作成', false);

        $this->actingAs($editor)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('お知らせを作成', false);
    }

    public function test_dashboard_edit_permissions_for_executive_and_administrative_affairs(): void
    {
        $executive = $this->executiveUser();
        $administrativeAffairs = $this->administrativeAffairsUser();

        $this->assertTrue($executive->canManageDashboardContents());
        $this->assertTrue($administrativeAffairs->canManageDashboardContents());

        $this->actingAs($executive)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('リンクを編集', false);

        $this->actingAs($administrativeAffairs)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('お知らせを作成', false);
    }

    public function test_designated_dashboard_manager_email_can_view_other_department_tabs(): void
    {
        $manager = User::factory()->create([
            'email' => 'ginga_fukui@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $manager->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '東京支店,経営企画室,人事部',
            'position' => '支店長 兼 人事部 部長',
            'location' => '大阪',
        ]);

        $manager = $manager->fresh();

        $this->assertTrue($manager->isDesignatedDashboardManager());
        $this->assertTrue($manager->canManageDashboardContents());
        $this->assertFalse(DashboardTab::canViewTab($manager, 'telecom'));

        $this->actingAs($manager)
            ->get(route('dashboard', ['tab' => 'telecom']))
            ->assertOk()
            ->assertDontSee('在籍部署が一致しないため', false)
            ->assertSee('お知らせを作成', false);
    }

    public function test_regular_employee_cannot_access_announcement_editor(): void
    {
        $employee = $this->userInDepartment('通信部');

        $this->actingAs($employee)
            ->get(route('dashboard.announcements.create', ['tab' => 'common']))
            ->assertForbidden();
    }

    public function test_image_upload_stores_file_in_dashboard_storage(): void
    {
        Storage::fake('public');

        $user = $this->informationSystemsUser();

        $response = $this->actingAs($user)->post(route('dashboard.content.images.store'), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
            'department' => '社員共通',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['location']);
        $this->assertCount(1, Storage::disk('public')->files('dashboard/images'));
    }

    public function test_regular_employee_cannot_upload_dashboard_images(): void
    {
        Storage::fake('public');

        $employee = $this->userInDepartment('通信部');

        $this->actingAs($employee)
            ->post(route('dashboard.content.images.store'), [
                'image' => UploadedFile::fake()->image('photo.jpg'),
                'department' => '社員共通',
            ])
            ->assertForbidden();
    }

    public function test_announcement_can_persist_uploaded_image_html(): void
    {
        Storage::fake('public');

        $user = $this->informationSystemsUser();
        $imageUrl = route('dashboard-contents.asset', ['path' => 'images/sample.jpg']);

        $this->actingAs($user)
            ->post(route('dashboard.announcements.store'), [
                'department' => '社員共通',
                'content_html' => '<p><img src="'.$imageUrl.'" alt="sample"></p>',
                'tab' => 'common',
                'is_visible' => '1',
            ])
            ->assertRedirect(route('dashboard', ['tab' => 'common']));

        $content = DashboardContent::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee($imageUrl, false);

        $this->assertStringContainsString('<img', $content->resolvedHtml());
    }

    public function test_non_matching_department_cannot_view_announcement(): void
    {
        $user = $this->userInDepartment('通信部');

        DashboardContent::query()->create([
            'department' => '食品',
            'content_html' => '<p>食品向け</p>',
            'is_visible' => true,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'food']))
            ->assertOk()
            ->assertDontSee('食品向け', false)
            ->assertSee('在籍部署が一致しないため', false);
    }

    public function test_real_estate_tab_is_visible_only_for_real_estate_department(): void
    {
        $realEstateUser = $this->userInDepartment('不動産部');
        $otherUser = $this->userInDepartment('通信部');

        $this->assertTrue(DashboardTab::canViewTab($realEstateUser, 'real-estate'));
        $this->assertFalse(DashboardTab::canViewTab($otherUser, 'real-estate'));
    }

    public function test_dashboard_shows_real_estate_link_for_real_estate_department(): void
    {
        $realEstateUser = $this->userInDepartment('不動産部');

        $this->actingAs($realEstateUser)
            ->get(route('dashboard', ['tab' => 'real-estate']))
            ->assertOk()
            ->assertSee('不動産社内サイト', false);
    }

    public function test_common_tab_shows_employee_list_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('社員一覧', false);
    }

    public function test_dashboard_shows_edit_links_action_for_editors(): void
    {
        $user = $this->informationSystemsUser();

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('リンクを編集', false);
    }

    public function test_dashboard_shows_announcements_before_links(): void
    {
        Storage::fake('public');

        $user = $this->userInDepartment('通信部');

        DashboardContent::persistHtml('社員共通', '<p>先に表示するお知らせ</p>', $user->id);

        $html = $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('先に表示するお知らせ', false)
            ->assertSee('社員一覧', false)
            ->getContent();

        $this->assertLessThan(
            strpos($html, '社員一覧'),
            strpos($html, '先に表示するお知らせ'),
        );
    }

    public function test_user_can_edit_dashboard_links_for_tab(): void
    {
        $user = $this->informationSystemsUser();

        $this->actingAs($user)
            ->get(route('dashboard.links.edit', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('リンク編集', false)
            ->assertSee('社員一覧', false);

        $this->actingAs($user)
            ->put(route('dashboard.links.update'), [
                'tab' => 'common',
                'links' => [
                    [
                        'label' => 'カスタムリンク',
                        'url' => '/employees',
                        'kind' => 'link',
                        'sort_order' => 10,
                        'is_visible' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('dashboard', ['tab' => 'common']));

        $this->assertDatabaseHas('dashboard_links', [
            'tab_key' => 'common',
            'label' => 'カスタムリンク',
            'url' => '/employees',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('カスタムリンク', false)
            ->assertDontSee('備品購入精算', false);
    }

    public function test_dashboard_links_are_saved_in_submitted_order(): void
    {
        $user = $this->informationSystemsUser();

        $this->actingAs($user)
            ->put(route('dashboard.links.update'), [
                'tab' => 'common',
                'links' => [
                    [
                        'label' => '二番目にしたいリンク',
                        'url' => '/second',
                        'kind' => 'link',
                        'sort_order' => 999,
                        'is_visible' => '1',
                    ],
                    [
                        'label' => '一番目にしたいリンク',
                        'url' => '/first',
                        'kind' => 'link',
                        'sort_order' => 1,
                        'is_visible' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('dashboard', ['tab' => 'common']));

        $labels = DashboardLink::query()
            ->forTab('common')
            ->ordered()
            ->pluck('label')
            ->all();

        $this->assertSame([
            '二番目にしたいリンク',
            '一番目にしたいリンク',
        ], $labels);

        $this->assertDatabaseHas('dashboard_links', [
            'tab_key' => 'common',
            'label' => '二番目にしたいリンク',
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('dashboard_links', [
            'tab_key' => 'common',
            'label' => '一番目にしたいリンク',
            'sort_order' => 20,
        ]);
    }

    public function test_editors_can_view_other_department_announcements(): void
    {
        $editor = $this->informationSystemsUser();

        DashboardContent::query()->create([
            'department' => '食品',
            'content_html' => '<p>食品向け</p>',
            'is_visible' => true,
            'updated_by' => $editor->id,
        ]);

        $this->actingAs($editor)
            ->get(route('dashboard', ['tab' => 'food']))
            ->assertOk()
            ->assertSee('食品向け', false)
            ->assertDontSee('在籍部署が一致しないため', false);
    }

    private function informationSystemsUser(): User
    {
        return $this->userInDepartment('情報システム部', '事業IT推進課');
    }

    private function administrativeAffairsUser(): User
    {
        return $this->userInDepartment('管理本部', '庶務課');
    }

    private function executiveUser(): User
    {
        return $this->userInAffiliation('役員', '役員', '代表');
    }

    private function userInAffiliation(string $department, string $section, string $position): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'position' => $position,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }

    private function userInDepartment(string $department, string $section = '営業課'): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}
