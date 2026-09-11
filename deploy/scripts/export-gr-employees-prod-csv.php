<?php

declare(strict_types=1);

$sqlFile = __DIR__.'/list-gr-employees-prod.sql';
$outputFile = dirname(__DIR__).'/gr-employees-prod.csv';

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

function csvField(string $value): string
{
    return '"'.str_replace('"', '""', $value).'"';
}

$handle = fopen($outputFile, 'w');
if ($handle === false) {
    fwrite(STDERR, "Failed to open output file: {$outputFile}\n");
    exit(1);
}

fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($handle, [
    '社員ID',
    '氏名',
    'HR部署',
    'HR管轄',
    '所属部署',
    '所属管轄',
    'CSV部署',
]);

require __DIR__.'/../../vendor/autoload.php';

use App\Support\RegistryGrAssignment;

$count = 0;

foreach ($rows as $row) {
    $values = array_map(static fn (array $cell): string => isset($cell['nullValue']) ? '' : (string) ($cell['value'] ?? ''), $row['values']);
    [$employeeId, $nameHex, $deptHex, $jurHex, $affDeptHex, $affLocHex] = $values;

    $name = decodeHex($nameHex);
    $dept = decodeHex($deptHex);
    $jur = decodeHex($jurHex);
    $affDept = decodeHex($affDeptHex);
    $affLoc = decodeHex($affLocHex);

    $sourceDept = $dept !== '' ? $dept : $affDept;
    $sourceJur = $jur !== '' ? $jur : $affLoc;
    $csvDept = RegistryGrAssignment::rosterDepartmentLabel($sourceJur, $sourceDept);

    fputcsv($handle, [
        $employeeId,
        $name,
        $dept,
        $jur,
        $affDept,
        $affLoc,
        $csvDept,
    ]);

    $count++;
}

fclose($handle);

echo "Wrote {$count} rows to {$outputFile}\n";
