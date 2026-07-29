<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipmentPurchaseApproveRequest;
use App\Http\Requests\EquipmentPurchaseConsumableUpdateRequest;
use App\Http\Requests\EquipmentPurchaseOrderUpdateRequest;
use App\Http\Requests\EquipmentPurchaseStoreRequest;
use App\Models\AffiliationHistory;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use App\Services\EquipmentPurchaseCsvExporter;
use App\Services\EquipmentPurchaseSubmissionPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentPurchaseController extends Controller
{
    private const LIST_PER_PAGE = 100;

    public function __construct(
        private EquipmentPurchaseCsvExporter $csvExporter,
    ) {}

    public function index(): View
    {
        $this->authorizeEquipmentPurchaseSettlement();

        return view('equipment-purchase.index', [
            'canSubmit' => EquipmentPurchaseSubmissionPeriod::canSubmitToday(),
            'submissionDeadlineMessage' => EquipmentPurchaseSubmissionPeriod::deadlineMessage(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorizeEquipmentPurchaseSettlement();

        if (! EquipmentPurchaseSubmissionPeriod::canSubmitToday()) {
            return redirect()
                ->route('equipment-purchases.index')
                ->with('error', EquipmentPurchaseSubmissionPeriod::closedMessage());
        }

        $type = $request->query('type');

        if (! is_string($type) || ! array_key_exists($type, EquipmentPurchaseApplication::TYPE_LABELS)) {
            return redirect()->route('equipment-purchases.index');
        }

        return view('equipment-purchase.create', [
            'type' => $type,
            'typeLabel' => EquipmentPurchaseApplication::TYPE_LABELS[$type],
            'user' => auth()->user(),
            'submissionDeadlineMessage' => EquipmentPurchaseSubmissionPeriod::deadlineMessage(),
        ]);
    }

    public function store(EquipmentPurchaseStoreRequest $request): RedirectResponse
    {
        $this->authorizeEquipmentPurchaseSettlement();
        $application = auth()->user()->equipmentPurchaseApplications()->create([
            ...$request->validated(),
            'application_date' => EquipmentPurchaseSubmissionPeriod::resolveApplicationDate(),
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
        ]);

        $applicationId = $application->id;
        dispatch(function () use ($applicationId): void {
            $application = EquipmentPurchaseApplication::query()->find($applicationId);
            if ($application === null) {
                return;
            }

            app(EquipmentPurchaseApprovalNotifier::class)->notifySubmitted($application);
        })->afterResponse();

        return redirect()->route('equipment-purchases.complete', $application);
    }

    public function complete(EquipmentPurchaseApplication $equipmentPurchase): View
    {
        if ($equipmentPurchase->user_id !== auth()->id()) {
            abort(403);
        }

        return view('equipment-purchase.complete', [
            'application' => $equipmentPurchase,
        ]);
    }

    public function pending(): View
    {
        $this->authorizeEquipmentPurchaseAccess();

        $user = auth()->user();

        // メール通知と同じ canApproveEquipmentPurchase() で絞る（社内・現場用・緊急購入すべて）
        $applications = EquipmentPurchaseApplication::query()
            ->with(['user.affiliationHistories'])
            ->where('status', EquipmentPurchaseApplication::STATUS_PENDING)
            ->orderByDesc('application_date')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (EquipmentPurchaseApplication $application) => $user->canApproveEquipmentPurchase($application))
            ->values();

        return view('equipment-purchase.pending', [
            'applications' => $applications,
        ]);
    }

    public function approve(EquipmentPurchaseApplication $equipmentPurchase): View
    {
        $this->authorizeCanApprove($equipmentPurchase);

        return view('equipment-purchase.approve', [
            'application' => $equipmentPurchase->load('user'),
        ]);
    }

    public function updateApproval(EquipmentPurchaseApproveRequest $request, EquipmentPurchaseApplication $equipmentPurchase): RedirectResponse
    {
        $this->authorizeCanApprove($equipmentPurchase);
        if (! $equipmentPurchase->isPending()) {
            return redirect()
                ->route('equipment-purchases.pending')
                ->with('error', 'この申請はすでに承認処理済みです。');
        }

        $decision = $request->validated('approval_decision');
        $isApproved = $decision === EquipmentPurchaseApplication::DECISION_APPROVED;
        $approvedAt = Carbon::parse($request->validated('approved_at'), config('app.timezone'));

        $confirmerName = auth()->user()->equipmentApprovalConfirmName(
            $request->validated('approver_display_name'),
        );

        if ($equipmentPurchase->isAwaitingFirstApproval()) {
            $equipmentPurchase->update([
                'first_approved_at' => $approvedAt,
                'first_approver_id' => auth()->id(),
                'first_approver_display_name' => $confirmerName,
                'first_approval_decision' => $decision,
                'first_approval_memo' => $request->validated('approval_memo'),
                'status' => $isApproved
                    ? EquipmentPurchaseApplication::STATUS_PENDING
                    : EquipmentPurchaseApplication::STATUS_REJECTED,
            ]);

            if ($isApproved) {
                $applicationId = $equipmentPurchase->id;
                dispatch(function () use ($applicationId): void {
                    $application = EquipmentPurchaseApplication::query()->find($applicationId);
                    if ($application === null) {
                        return;
                    }

                    app(EquipmentPurchaseApprovalNotifier::class)->notifySecondStage($application);
                })->afterResponse();

                return redirect()
                    ->route('equipment-purchases.pending')
                    ->with('success', '1次承認（部長）を完了しました。支店長の承認を待っています。');
            }

            return redirect()
                ->route('equipment-purchases.pending')
                ->with('success', '申請を許可しませんでした。');
        }

        $equipmentPurchase->update([
            'approved_at' => $approvedAt,
            'approver_id' => auth()->id(),
            'approver_display_name' => $confirmerName,
            'approval_decision' => $decision,
            'approval_memo' => $request->validated('approval_memo'),
            'status' => $isApproved
                ? EquipmentPurchaseApplication::STATUS_APPROVED
                : EquipmentPurchaseApplication::STATUS_REJECTED,
        ]);

        return redirect()
            ->route('equipment-purchases.pending')
            ->with('success', $isApproved ? '申請を許可しました。' : '申請を許可しませんでした。');
    }

    public function list(Request $request): View
    {
        $this->authorizeListAccess();

        $filters = $this->listFilters($request);
        $query = $this->filteredQuery($filters);

        return view('equipment-purchase.list', [
            'applications' => $query->paginate(self::LIST_PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'departments' => $this->departmentOptions(),
            'locations' => User::OFFICE_LOCATIONS,
            'totalCount' => (clone $query)->count(),
            'showsOnlyOwnApplications' => ! auth()->user()->seesAllEquipmentPurchaseList(),
            'canEditConsumable' => auth()->user()->isGeneralAffairs(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeListAccess();

        $applications = $this->filteredQuery($this->listFilters($request))->get();

        return response()->streamDownload(
            fn () => $this->csvExporter->stream($applications),
            $this->csvExporter->filename(),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function show(EquipmentPurchaseApplication $equipmentPurchase): View
    {
        $this->authorizeCanViewApplication($equipmentPurchase);

        return view('equipment-purchase.show', [
            'application' => $equipmentPurchase->load(['user', 'approver', 'firstApprover', 'orderer']),
            'canEditOrder' => auth()->user()->canUpdateEquipmentPurchaseOrder($equipmentPurchase),
        ]);
    }

    public function updateOrder(EquipmentPurchaseOrderUpdateRequest $request, EquipmentPurchaseApplication $equipmentPurchase): RedirectResponse
    {
        $equipmentPurchase->update([
            'order_date' => $request->validated('order_date'),
            'arrival_date' => $request->validated('arrival_date'),
            'receipt_issued' => $request->boolean('receipt_issued'),
            'orderer_id' => auth()->id(),
        ]);

        return redirect()
            ->route('equipment-purchases.show', $equipmentPurchase)
            ->with('success', '発注情報を保存しました。');
    }

    public function updateConsumable(
        EquipmentPurchaseConsumableUpdateRequest $request,
        EquipmentPurchaseApplication $equipmentPurchase
    ): RedirectResponse {
        $equipmentPurchase->update([
            'is_consumable' => $request->boolean('is_consumable'),
        ]);

        return redirect()
            ->back()
            ->with('success', '消耗品の設定を保存しました。');
    }

    /**
     * @return array{
     *     department: ?string,
     *     location: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     keyword: ?string,
     *     price_min: ?int,
     *     price_max: ?int,
     * }
     */
    private function listFilters(Request $request): array
    {
        $priceMin = $request->string('price_min')->toString();
        $priceMax = $request->string('price_max')->toString();

        return [
            'department' => $request->string('department')->toString() ?: null,
            'location' => $request->string('location')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'keyword' => $request->string('keyword')->toString() ?: null,
            'price_min' => is_numeric($priceMin) ? (int) $priceMin : null,
            'price_max' => is_numeric($priceMax) ? (int) $priceMax : null,
        ];
    }

    /**
     * @param  array{
     *     department: ?string,
     *     location: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     keyword: ?string,
     *     price_min: ?int,
     *     price_max: ?int,
     * }  $filters
     */
    private function filteredQuery(array $filters)
    {
        $user = auth()->user();

        $query = EquipmentPurchaseApplication::query()
            ->with(['user', 'approver', 'firstApprover', 'orderer'])
            ->filtered($filters)
            ->orderByDesc('application_date')
            ->orderByDesc('created_at');

        if (! $user->seesAllEquipmentPurchaseList()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /** @return list<string> */
    private function departmentOptions(): array
    {
        $fromApplications = EquipmentPurchaseApplication::query()
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department');

        $fromAffiliations = AffiliationHistory::query()
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department');

        return $fromApplications
            ->merge($fromAffiliations)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function authorizeEquipmentPurchaseSettlement(): void
    {
        if (! auth()->user()->canAccessEquipmentPurchaseSettlement()) {
            abort(403);
        }
    }

    private function authorizeEquipmentPurchaseAccess(): void
    {
        if (! auth()->user()->canManageEquipmentPurchases()) {
            abort(403);
        }
    }

    private function authorizeListAccess(): void
    {
        if (! auth()->user()->canViewEquipmentPurchaseList()) {
            abort(403);
        }
    }

    private function authorizeCanViewApplication(EquipmentPurchaseApplication $application): void
    {
        if (! auth()->user()->canViewEquipmentPurchaseApplication($application)) {
            abort(403);
        }
    }

    private function authorizeCanApprove(EquipmentPurchaseApplication $application): void
    {
        if (! auth()->user()->canApproveEquipmentPurchase($application)) {
            abort(403);
        }
    }
}
