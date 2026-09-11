<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use App\Support\RegistryOrgAssignment;

$sqlFile = __DIR__.'/list-gr-sections-prod.sql';
$cmd = 'gcloud sql instances execute-sql employee'
    .' --database=ceemployee'
    .' --project=ce-gr-employee-info-2606st'
    .' --format=json'
    .' --sql=@'.$sqlFile;

$data = json_decode((string) shell_exec($cmd), true);
$rows = $data['results'][0]['rows'] ?? [];

function decodeHex(?string $hex): string
{
    $hex = trim((string) $hex);

    return $hex === '' ? '' : (hex2bin($hex) ?: '');
}

function encodeHex(?string $value): string
{
    $value = (string) $value;

    return $value === '' ? '' : strtoupper(bin2hex($value));
}

function sqlString(?string $value): string
{
    $value = (string) $value;

    if ($value === '') {
        return 'NULL';
    }

    return "UNHEX('".encodeHex($value)."')";
}

$apply = in_array('--apply', $argv, true);
$changes = [];
$sqlStatements = [];

foreach ($rows as $row) {
    $values = array_map(static fn (array $cell): string => isset($cell['nullValue']) ? '' : (string) ($cell['value'] ?? ''), $row['values']);
    [$employeeId, $nameHex, $deptHex, $jurHex, $sectionHex, $affSectionHex, $affDeptHex, $affLocHex, $hasActiveAffHex] = array_pad($values, 9, '');

    $name = decodeHex($nameHex);
    $dept = decodeHex($deptHex);
    $jur = decodeHex($jurHex);
    $section = decodeHex($sectionHex);
    $affSection = decodeHex($affSectionHex);
    $affDept = decodeHex($affDeptHex);
    $affLoc = decodeHex($affLocHex);

    [$newDept, $newJur, $newSection] = RegistryOrgAssignment::normalizePrimaryOrgStorage($dept, $jur, $section);

    $affChanged = false;
    $newAffDept = $affDept;
    $newAffLoc = $affLoc;
    $newAffSection = $affSection;

    if ($hasActiveAffHex !== '') {
        [$newAffDept, $newAffLoc, $newAffSection] = RegistryOrgAssignment::normalizePrimaryOrgStorage(
            $affDept,
            $affLoc,
            $affSection,
        );

        if ($affDept !== '' && $affDept !== (string) $newAffDept) {
            $affChanged = true;
        }

        if ($affLoc !== '' && $affLoc !== (string) ($newAffLoc ?? '')) {
            $affChanged = true;
        }

        // 所属の課・チームが空の場合は HR 詳細から補完しない。
        if ($affSection !== '' && $affSection !== (string) ($newAffSection ?? '')) {
            $affChanged = true;
        }
    }

    $hrChanged = ($dept !== (string) $newDept)
        || ($jur !== (string) ($newJur ?? ''))
        || ($section !== (string) ($newSection ?? ''));

    if (! $hrChanged && ! $affChanged) {
        continue;
    }

    $changes[] = [
        'employeeId' => $employeeId,
        'name' => $name,
        'hr' => [$dept, $jur, $section],
        'newHr' => [$newDept, $newJur ?? '', $newSection ?? ''],
        'aff' => [$affDept, $affLoc, $affSection],
        'newAff' => [$newAffDept, $newAffLoc ?? '', $newAffSection ?? ''],
    ];

    if ($hrChanged) {
        $sqlStatements[] = "UPDATE employee_hr_details ehd"
            .' INNER JOIN users u ON u.id = ehd.user_id'
            .' SET ehd.department_primary = '.sqlString($newDept)
            .', ehd.jurisdiction = '.sqlString($newJur)
            .', ehd.section_primary = '.sqlString($newSection)
            ." WHERE u.employee_id = '{$employeeId}';";
    }

    if ($affChanged) {
        $affSets = [];

        if ($affDept !== '' && $affDept !== (string) $newAffDept) {
            $affSets[] = 'ah.department = '.sqlString($newAffDept);
        }

        if ($affLoc !== '' && $affLoc !== (string) ($newAffLoc ?? '')) {
            $affSets[] = 'ah.location = '.sqlString($newAffLoc);
        }

        if ($affSection !== '' && $affSection !== (string) ($newAffSection ?? '')) {
            $affSets[] = 'ah.section = '.sqlString($newAffSection);
        }

        if ($affSets !== []) {
            $sqlStatements[] = 'UPDATE affiliation_histories ah'
                .' INNER JOIN users u ON u.id = ah.user_id'
                .' SET '.implode(', ', $affSets)
                ." WHERE u.employee_id = '{$employeeId}'"
                ." AND ah.enrollment_status = '在籍中'"
                .' AND (ah.end_date IS NULL OR ah.end_date >= CURDATE());';
        }
    }
}

echo 'needs_update: '.count($changes)."\n";

foreach (array_slice($changes, 0, 40) as $change) {
    echo implode("\t", [
        $change['employeeId'],
        $change['name'],
        'HR:'.$change['hr'][2].' => '.$change['newHr'][2],
        'AFF:'.$change['aff'][2].' => '.$change['newAff'][2],
    ])."\n";
}

if (count($changes) > 40) {
    echo '... '.(count($changes) - 40)." more\n";
}

if ($sqlStatements === []) {
    echo "\nNo SQL updates required.\n";
    exit(0);
}

$sqlPath = __DIR__.'/normalize-gr-sections-prod.generated.sql';
file_put_contents($sqlPath, implode(PHP_EOL, $sqlStatements).PHP_EOL);

echo "\nGenerated SQL: {$sqlPath}\n";
echo 'Statements: '.count($sqlStatements)."\n";

if (! $apply) {
    echo "Dry run only. Re-run with --apply to execute.\n";
    exit(0);
}

foreach ($sqlStatements as $statement) {
    $cmd = 'gcloud sql instances execute-sql employee'
        .' --database=ceemployee'
        .' --project=ce-gr-employee-info-2606st'
        .' --format=json'
        .' --sql='.escapeshellarg($statement);

    $result = shell_exec($cmd);

    if ($result === null) {
        fwrite(STDERR, "Failed statement for: {$statement}\n");
        exit(1);
    }
}

echo "Applied ".count($sqlStatements)." statements.\n";
