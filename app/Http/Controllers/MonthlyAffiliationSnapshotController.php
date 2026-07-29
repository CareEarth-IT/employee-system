<?php

namespace App\Http\Controllers;

use App\Models\MonthlyAffiliationRecord;
use App\Services\MonthlyAffiliationCsvExporter;
use App\Services\MonthlyAffiliationSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyAffiliationSnapshotController extends Controller
{
    public function __construct(
        private MonthlyAffiliationSnapshotService $snapshots,
        private MonthlyAffiliationCsvExporter $csvExporter,
    ) {}

    public function index(): View
    {
        $this->authorizeViewer();

        $months = $this->snapshots->savedMonths();
        $currentMonth = now()->timezone(config('app.timezone'))->format('Y-m');

        return view('monthly-affiliations.index', [
            'months' => $months,
            'currentMonth' => $currentMonth,
            'currentMonthSaved' => $this->snapshots->hasMonth($currentMonth),
        ]);
    }

    public function show(string $yearMonth): View
    {
        $this->authorizeViewer();

        if (! MonthlyAffiliationRecord::isValidYearMonth($yearMonth)) {
            abort(404);
        }

        $records = $this->snapshots->recordsForMonth($yearMonth);

        if ($records->isEmpty()) {
            abort(404);
        }

        return view('monthly-affiliations.show', [
            'yearMonth' => $yearMonth,
            'yearMonthLabel' => MonthlyAffiliationRecord::formatYearMonthLabel($yearMonth),
            'records' => $records,
            'capturedAt' => $records->first()->captured_at,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeViewer();

        $yearMonth = (string) $request->input('year_month', now()->timezone(config('app.timezone'))->format('Y-m'));

        if (! MonthlyAffiliationRecord::isValidYearMonth($yearMonth)) {
            return redirect()
                ->route('monthly-affiliations.index')
                ->with('error', '対象月の形式が正しくありません。');
        }

        if ($this->snapshots->hasMonth($yearMonth) && ! $request->boolean('overwrite')) {
            return redirect()
                ->route('monthly-affiliations.index')
                ->with('error', MonthlyAffiliationRecord::formatYearMonthLabel($yearMonth).'のデータは既に保存されています。上書きする場合は確認にチェックを入れてください。');
        }

        $count = $this->snapshots->capture($yearMonth, auth()->user());

        return redirect()
            ->route('monthly-affiliations.show', $yearMonth)
            ->with('success', MonthlyAffiliationRecord::formatYearMonthLabel($yearMonth)."の所属情報を {$count} 名分保存しました。")
            ->with('auto_export', true);
    }

    public function export(string $yearMonth): StreamedResponse
    {
        $this->authorizeViewer();

        if (! MonthlyAffiliationRecord::isValidYearMonth($yearMonth)) {
            abort(404);
        }

        $records = $this->snapshots->recordsForMonth($yearMonth);

        if ($records->isEmpty()) {
            abort(404);
        }

        return response()->streamDownload(
            fn () => $this->csvExporter->stream($records),
            $this->csvExporter->filename($yearMonth),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeViewer(): void
    {
        if (! auth()->user()->canViewMonthlyAffiliationSnapshots()) {
            abort(403);
        }
    }
}
