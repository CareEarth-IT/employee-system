<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeCsvImportRequest;
use App\Services\EmployeeBulkImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->isInformationSystems(), 403);

        return view('employees.import');
    }

    public function template(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isInformationSystems(), 403);

        $filename = 'employee_import_template.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', '氏名', '姓', '名', '社員番号', '部', '課', '役職', '拠点', '会社', '電話番号', 'パスワード']);
            fputcsv($out, [
                'example@careearth.info',
                '山田 花子',
                '山田',
                '花子',
                'EMP0100',
                '通信部',
                '事業IT推進課',
                '一般',
                '大阪',
                'CareEarth',
                '080-0000-0000',
                '',
            ]);
            fclose($out);
        }, $filename, $headers);
    }

    public function store(EmployeeCsvImportRequest $request, EmployeeBulkImporter $importer): RedirectResponse
    {
        $uploaded = $request->file('csv');
        $tempPath = $uploaded->getRealPath();

        if ($tempPath === false || ! is_readable($tempPath)) {
            return back()->withErrors(['csv' => 'CSVファイルを読み込めませんでした。']);
        }

        // Keep a copy with a stable extension for CSV readers.
        $workingPath = tempnam(sys_get_temp_dir(), 'employee-import-');
        $csvPath = $workingPath.'.csv';
        @unlink($workingPath);
        if (! @copy($tempPath, $csvPath)) {
            return back()->withErrors(['csv' => 'CSVファイルの一時保存に失敗しました。']);
        }

        try {
            // Web UI is create-only: never pass sync/force flags so existing rows stay unchanged.
            $result = $importer->import($csvPath);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['csv' => $e->getMessage()]);
        } finally {
            @unlink($csvPath);
        }

        if ($result->failed()) {
            return back()
                ->withErrors(['csv' => implode("\n", $result->errors)])
                ->with('import_rows', $result->rows);
        }

        return redirect()
            ->route('employees.import.create')
            ->with('success', $result->summaryMessage())
            ->with('import_rows', $result->rows);
    }
}
