<?php

namespace App\Http\Controllers;

use App\Http\Requests\DevelopmentRequestStoreRequest;
use App\Http\Requests\DevelopmentRequestUpdateRequest;
use App\Models\DevelopmentRequest;
use App\Services\DevelopmentRequestChatNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DevelopmentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $type = trim((string) $request->query('type', ''));
        $date = trim((string) $request->query('date', ''));
        $embed = $request->boolean('embed');
        $user = $request->user();
        $canViewDetail = (bool) $user?->canViewDevelopmentRequestDetail();

        if ($embed) {
            $requests = DevelopmentRequest::query()
                ->withinListMonths()
                ->orderByDesc('created_at')
                ->orderByDesc('request_number')
                ->get();

            $listItems = $requests->map(static function (DevelopmentRequest $item): array {
                return [
                    'id' => $item->request_number,
                    'requestDate' => $item->request_date?->format('y/m/d') ?? '',
                    'department' => (string) ($item->requester_department ?: ''),
                    'requesterName' => (string) ($item->requester_name ?: ''),
                    'contentTypeLabel' => $item->contentTypeLabel(),
                    'title' => (string) $item->title,
                    'titleShort' => $item->titleShort(),
                    'progress' => (string) ($item->progress ?: ''),
                    'remarks' => (string) ($item->remarks ?? ''),
                    'estimatedHours' => $item->estimated_hours !== null && $item->estimated_hours !== ''
                        ? (string) $item->estimated_hours
                        : '',
                    'actualHours' => $item->actual_hours !== null && $item->actual_hours !== ''
                        ? (string) $item->actual_hours
                        : '',
                    'devTarget' => $item->development_target_date?->format('y/m/d') ?? '',
                    'updatedAt' => $item->updated_at?->format('y/m/d') ?? '',
                    'devAssignee' => (string) ($item->development_assignee ?: '未'),
                    'manager' => (string) ($item->manager ?: ''),
                ];
            })->values()->all();

            return view('development-requests.index-embed', [
                'listItems' => $listItems,
                'detailAccess' => [
                    'canView' => $canViewDetail,
                    'canEdit' => (bool) $user?->canEditDevelopmentRequest(),
                ],
                'createUrl' => route('development-requests.create', ['embed' => 1]),
                'listUrl' => route('development-requests.index', ['embed' => 1]),
                'detailUrlTemplate' => route('development-requests.show', [
                    'developmentRequest' => '__ID__',
                    'embed' => 1,
                ]),
                'typeFilters' => [
                    '派遣開発',
                    '派遣以外',
                    'ソフト/Google',
                    '新規',
                    'PC/Wifi/スマホ',
                    'その他',
                    'Airtable',
                    '完了',
                ],
            ]);
        }

        $query = DevelopmentRequest::query()
            ->withinListMonths()
            ->orderByDesc('created_at')
            ->orderByDesc('request_number');

        if ($type === '完了') {
            $query->where('progress', '完了');
        } else {
            $query->where('progress', '!=', '完了');
            if ($type !== '') {
                $stored = array_search($type, DevelopmentRequest::CONTENT_TYPE_LABELS, true);
                if (is_string($stored)) {
                    $query->where('content_type', $stored);
                }
            }
        }

        if ($date !== '') {
            $query->whereDate('request_date', $date);
        }

        return view('development-requests.index', [
            'requests' => $query->get(),
            'activeType' => $type,
            'activeDate' => $date,
            'canViewDetail' => $canViewDetail,
            'embed' => false,
            'typeFilters' => [
                '派遣開発',
                '派遣以外',
                'ソフト/Google',
                '新規',
                'PC/Wifi/スマホ',
                'その他',
                'Airtable',
                '完了',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = auth()->user();

        return view('development-requests.create', [
            'user' => $user,
            'requesterName' => $user->displayName(),
            'requesterDepartment' => $user->currentDepartment() ?? '',
            'requesterNumber' => (string) ($user->employee_id ?? ''),
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function store(DevelopmentRequestStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employeeId = trim((string) ($user->employee_id ?? ''));
        $embed = $request->boolean('embed');

        if ($employeeId === '') {
            return back()
                ->withInput()
                ->with('error', '社員番号（依頼者番号）が登録されていません。プロフィールの社員IDを確認してください。');
        }

        $validated = $request->validated();
        $contentType = $validated['content_type'];

        $developmentRequest = DB::transaction(function () use ($user, $employeeId, $validated, $contentType) {
            return DevelopmentRequest::create([
                'request_number' => DevelopmentRequest::nextRequestNumber(),
                'user_id' => $user->id,
                'requester_name' => $user->displayName(),
                'requester_department' => $user->currentDepartment(),
                'requester_number' => $employeeId,
                'requester_email' => (string) $user->email,
                'request_date' => $validated['request_date'],
                'content_type' => $contentType,
                'sub_type' => null,
                'title' => $validated['title'],
                'purpose' => $validated['purpose'],
                'detail' => $validated['detail'],
                'progress' => '相談前',
                'development_assignee' => '未',
                'manager' => DevelopmentRequest::resolveManager($contentType),
            ]);
        });

        app(DevelopmentRequestChatNotifier::class)->notifySubmitted($developmentRequest);

        return redirect()
            ->route('development-requests.complete', array_filter([
                'developmentRequest' => $developmentRequest,
                'embed' => $embed ? 1 : null,
            ]))
            ->with('success', 'フォームが正常に送信されました。');
    }

    public function complete(Request $request, DevelopmentRequest $developmentRequest): View
    {
        if ($developmentRequest->user_id !== auth()->id()
            && ! auth()->user()->canViewDevelopmentRequestDetail()) {
            abort(403);
        }

        return view('development-requests.complete', [
            'request' => $developmentRequest,
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function show(Request $request, DevelopmentRequest $developmentRequest): View
    {
        if (! auth()->user()->canViewDevelopmentRequestDetail()) {
            abort(403, 'この詳細ページを表示する権限がありません。');
        }

        return view('development-requests.show', [
            'request' => $developmentRequest,
            'canEdit' => auth()->user()->canEditDevelopmentRequest(),
            'progressOptions' => DevelopmentRequest::PROGRESS_OPTIONS,
            'assigneeOptions' => DevelopmentRequest::DEV_ASSIGNEE_OPTIONS,
            'typeLabels' => array_values(array_unique(array_values(DevelopmentRequest::CONTENT_TYPE_LABELS))),
            'embed' => $request->boolean('embed'),
        ]);
    }

    public function update(
        DevelopmentRequestUpdateRequest $request,
        DevelopmentRequest $developmentRequest,
    ): RedirectResponse {
        $validated = $request->validated();
        $contentType = DevelopmentRequest::contentTypeFromLabel($validated['content_type_label']);
        $embed = $request->boolean('embed');

        $developmentRequest->update([
            'progress' => DevelopmentRequest::normalizeProgress($validated['progress']),
            'remarks' => $validated['remarks'] ?? null,
            'estimated_hours' => isset($validated['estimated_hours']) && $validated['estimated_hours'] !== ''
                ? (string) $validated['estimated_hours']
                : null,
            'actual_hours' => isset($validated['actual_hours']) && $validated['actual_hours'] !== ''
                ? (string) $validated['actual_hours']
                : null,
            'development_target_date' => $validated['development_target_date'] ?? null,
            'development_assignee' => $validated['development_assignee'],
            'content_type' => $contentType,
            'manager' => DevelopmentRequest::resolveManager($contentType),
        ]);

        return redirect()
            ->route('development-requests.index', array_filter(['embed' => $embed ? 1 : null]))
            ->with('success', '開発依頼を更新しました。');
    }
}
