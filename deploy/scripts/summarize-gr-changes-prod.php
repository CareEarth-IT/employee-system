<?php

declare(strict_types=1);

$sqlFile = __DIR__.'/list-gr-employees-prod.sql';
$cmd = 'gcloud sql instances execute-sql employee'
    .' --database=ceemployee'
    .' --project=ce-gr-employee-info-2606st'
    .' --format=json'
    .' --sql=@'.$sqlFile;

$json = shell_exec($cmd);
$data = json_decode($json, true);
$rows = $data['results'][0]['rows'] ?? [];

function decodeHex(?string $hex): string
{
    $hex = trim((string) $hex);

    return $hex === '' ? '' : (hex2bin($hex) ?: '');
}

$manual = [
    '10474' => ['before' => 'HR:大阪-GR部 / 管轄:空', 'after' => 'GR部（グローバル部） / 大阪'],
    '10482' => ['before' => 'HR:GR部（グローバル部） / 管轄:空', 'after' => 'GR部（グローバル部） / 名古屋'],
];

$comma = [];
$standard = [];

foreach ($rows as $row) {
    $values = array_map(static fn (array $cell): string => isset($cell['nullValue']) ? '' : (string) ($cell['value'] ?? ''), $row['values']);
    [$employeeId, $nameHex, $deptHex, $jurHex] = [$values[0], $values[1], $values[2], $values[3]];

    $name = decodeHex($nameHex);
    $dept = decodeHex($deptHex);
    $jur = decodeHex($jurHex);

    if (isset($manual[$employeeId])) {
        continue;
    }

    if (str_contains($dept, ',')) {
        $comma[] = [$employeeId, $name, $dept, $jur];

        continue;
    }

    $standard[] = [$employeeId, $name, $dept, $jur];
}

echo "=== 本番DBで今回変更した内容 ===\n\n";
echo "[1] 個別修正（2名）\n";
foreach ($manual as $employeeId => $change) {
    echo "  {$employeeId}\t{$change['before']}\t=>\t{$change['after']}\n";
}

echo "\n[2] 一括正規化（".count($standard)."名）\n";
echo "  旧: 大阪/東京/名古屋/福岡グローバル事業部、または 大阪-GR部 等\n";
echo "  新: GR部（グローバル部） + 管轄\n\n";

foreach ($standard as [$employeeId, $name, $dept, $jur]) {
    echo "  {$employeeId}\t{$name}\t{$dept}\t{$jur}\n";
}

echo "\n[3] 複数部署付き（GR部分のみ正規化、".count($comma)."名）\n";
foreach ($comma as [$employeeId, $name, $dept, $jur]) {
    echo "  {$employeeId}\t{$name}\t{$dept}\t{$jur}\n";
}

echo "\n[4] 今回変更していない（参考）\n";
echo "  10439, 10442 は実行時点で既に GR部（グローバル部）/東京 だったため未変更\n";
echo "\n合計変更: ".(2 + count($standard) + count($comma))."名\n";
