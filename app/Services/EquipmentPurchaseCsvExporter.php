<?php

namespace App\Services;

use App\Models\EquipmentPurchaseApplication;
use Illuminate\Support\Collection;

class EquipmentPurchaseCsvExporter
{
    /** @var list<string> */
    private const HEADERS = [
        '申請日',
        '申請タイプ',
        '申請者名',
        '購入商品名',
        '数量',
        '価格（税込）',
        '備考',
        '購入理由',
        '備品の利用先',
        '部',
        '課',
        '現場名',
    ];

    /**
     * @param  Collection<int, EquipmentPurchaseApplication>  $applications
     */
    public function stream(Collection $applications): void
    {
        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, self::HEADERS);

        foreach ($applications as $application) {
            fputcsv($handle, $this->row($application));
        }

        fclose($handle);
    }

    public function filename(): string
    {
        return 'equipment_purchases_'.now(config('app.timezone'))->format('Ymd_His').'.csv';
    }

    /**
     * @return list<mixed>
     */
    private function row(EquipmentPurchaseApplication $application): array
    {
        return [
            $application->application_date->format('Y/n/j'),
            $application->typeLabel(),
            $application->user->displayName(),
            $application->product_name,
            $application->quantity,
            $application->price_including_tax,
            $application->remarks,
            $application->purchase_reason,
            $application->itemDestinationLabel(),
            $application->department,
            $application->section,
            $application->onsite_name,
        ];
    }
}
