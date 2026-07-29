<?php

namespace App\Services;

use App\Models\MonthlyAffiliationRecord;
use Illuminate\Support\Collection;

class MonthlyAffiliationCsvExporter
{
    /** @var list<string> */
    private const HEADERS = [
        '名前',
        '社員ID',
        '拠点',
        '部',
        '課',
    ];

    /**
     * @param  Collection<int, MonthlyAffiliationRecord>  $records
     */
    public function stream(Collection $records): void
    {
        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, self::HEADERS);

        foreach ($records as $record) {
            fputcsv($handle, $this->row($record));
        }

        fclose($handle);
    }

    public function filename(string $yearMonth): string
    {
        return 'monthly_affiliations_'.$yearMonth.'.csv';
    }

    /**
     * @return list<string|null>
     */
    private function row(MonthlyAffiliationRecord $record): array
    {
        return [
            $record->name,
            $record->employee_id,
            $record->location,
            $record->department,
            $record->section,
        ];
    }
}
