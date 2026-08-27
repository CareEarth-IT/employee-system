<?php
/**
 * Compare Airtable 支援管理 CSV with portal support data (DB or SQL dump).
 *
 * Usage:
 *   php deploy/scripts/compare-support-csv.php "path/to/支援管理.csv"
 *   php deploy/scripts/compare-support-csv.php "path/to/支援管理.csv" --dump="path/to/specific_skills.dmp"
 */

$specificSkillsRoot = dirname(__DIR__, 2) . '/../specific_skills';
if (!is_dir($specificSkillsRoot)) {
    $specificSkillsRoot = 'c:/xampp/htdocs/specific_skills';
}

require_once $specificSkillsRoot . '/includes/csv_import.php';

$csvPath = '';
$dumpPath = '';
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($arg, '--dump=')) {
        $dumpPath = substr($arg, 7);
        continue;
    }
    if ($csvPath === '') {
        $csvPath = $arg;
    }
}

if ($csvPath === '' || !is_readable($csvPath)) {
    fwrite(STDERR, "Usage: php compare-support-csv.php <csv-path> [--dump=specific_skills.dmp]\n");
    exit(1);
}

/**
 * @return list<list<string|null>>
 */
function dumpParseSqlValues(string $valuesBlob): array
{
    $rows = [];
    $length = strlen($valuesBlob);
    $i = 0;

    while ($i < $length) {
        while ($i < $length && ($valuesBlob[$i] === ' ' || $valuesBlob[$i] === "\n" || $valuesBlob[$i] === "\r" || $valuesBlob[$i] === ',')) {
            $i++;
        }
        if ($i >= $length || $valuesBlob[$i] !== '(') {
            break;
        }
        $i++;

        $row = [];
        while ($i < $length) {
            while ($i < $length && ($valuesBlob[$i] === ' ' || $valuesBlob[$i] === "\n" || $valuesBlob[$i] === "\r")) {
                $i++;
            }

            if ($i < $length && $valuesBlob[$i] === ')') {
                $i++;
                if ($row !== []) {
                    $rows[] = $row;
                }
                break;
            }

            if ($i < $length && substr($valuesBlob, $i, 4) === 'NULL') {
                $row[] = null;
                $i += 4;
            } elseif ($i < $length && ($valuesBlob[$i] === "'" || $valuesBlob[$i] === '"')) {
                $quote = $valuesBlob[$i];
                $i++;
                $value = '';
                while ($i < $length) {
                    $ch = $valuesBlob[$i];
                    if ($ch === '\\' && $i + 1 < $length) {
                        $value .= $valuesBlob[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($ch === $quote) {
                        if ($i + 1 < $length && $valuesBlob[$i + 1] === $quote) {
                            $value .= $quote;
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $value .= $ch;
                    $i++;
                }
                $row[] = $value;
            } else {
                $start = $i;
                while ($i < $length && $valuesBlob[$i] !== ',' && $valuesBlob[$i] !== ')') {
                    $i++;
                }
                $row[] = trim(substr($valuesBlob, $start, $i - $start));
            }

            while ($i < $length && ($valuesBlob[$i] === ' ' || $valuesBlob[$i] === "\n" || $valuesBlob[$i] === "\r")) {
                $i++;
            }
            if ($i < $length && $valuesBlob[$i] === ',') {
                $i++;
            }
        }
    }

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function dumpLoadSupportRows(string $dumpPath): array
{
    $raw = file_get_contents($dumpPath);
    if ($raw === false) {
        throw new RuntimeException('Dump read failed: ' . $dumpPath);
    }

    if (!preg_match('/INSERT INTO `staff` VALUES\s*(.+?);\s*(?:\/\*|\n|$)/s', $raw, $m)) {
        throw new RuntimeException('staff INSERT not found in dump');
    }

    $parsed = dumpParseSqlValues($m[1]);
    $rows = [];

    foreach ($parsed as $fields) {
        if (count($fields) < 52) {
            continue;
        }

        $status = (string) ($fields[11] ?? '');
        $isDeleted = (string) ($fields[51] ?? '0');
        if (!in_array($status, ['支援中', '終了'], true) || $isDeleted !== '0') {
            continue;
        }

        $rows[] = [
            'staff_id' => (int) ($fields[0] ?? 0),
            'staff_name' => (string) ($fields[1] ?? ''),
            'kana_name' => (string) ($fields[2] ?? ''),
            'gender' => (string) ($fields[5] ?? ''),
            'birth_date' => (string) ($fields[6] ?? ''),
            'nationality' => (string) ($fields[7] ?? ''),
            'status' => $status,
            'recruitment_route' => (string) ($fields[12] ?? ''),
            'company_name' => (string) ($fields[13] ?? ''),
            'field_category' => (string) ($fields[14] ?? ''),
            'prefecture' => (string) ($fields[16] ?? ''),
            'workplace' => (string) ($fields[18] ?? ''),
            'facebook' => (string) ($fields[20] ?? ''),
            'join_date' => (string) ($fields[29] ?? ''),
            'support_end_date' => (string) ($fields[30] ?? ''),
            'jlpt_level' => (string) ($fields[42] ?? ''),
            'current_residence_status' => (string) ($fields[43] ?? ''),
            'notes' => (string) ($fields[47] ?? ''),
            'residence_expiry_after_permit' => (string) ($fields[50] ?? ''),
        ];
    }

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function dbLoadSupportRows(string $specificSkillsRoot): array
{
    require_once $specificSkillsRoot . '/includes/db.php';
    require_once $specificSkillsRoot . '/includes/repositories/SupportRepository.php';

    return (new SupportRepository())->fetchAll();
}

$columns = require $specificSkillsRoot . '/includes/support_columns.php';
[$labelToKey, $importableKeys, $dateKeys] = csvImportBuildMaps($columns);

$raw = file_get_contents($csvPath);
$matrix = csvImportParse($raw);
$headerCells = array_map(static fn ($v) => trim((string) $v), $matrix[0]);

$indexToKey = [];
foreach ($headerCells as $i => $header) {
    if ($header === '') {
        continue;
    }
    $mapped = csvImportResolveHeader($labelToKey, $header);
    if ($mapped !== null) {
        $indexToKey[$i] = $mapped;
    }
}

$unmappedHeaders = [];
foreach ($headerCells as $i => $header) {
    if ($header === '') {
        continue;
    }
    if (!isset($indexToKey[$i])) {
        $unmappedHeaders[] = $header;
    }
}

$compareKeys = array_values(array_filter($importableKeys, static function ($k) {
    return !in_array($k, ['status', 'current_residence_status', 'residence_card_front', 'residence_card_back', 'employment_contract_image', 'passport_image', 'pass_certificate_image'], true);
}));

$csvRows = [];
for ($r = 1, $rowCount = count($matrix); $r < $rowCount; $r++) {
    $cells = $matrix[$r];
    $data = [];
    foreach ($indexToKey as $i => $key) {
        $data[$key] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
    }
    foreach ($dateKeys as $dateField) {
        if (!array_key_exists($dateField, $data)) {
            continue;
        }
        $data[$dateField] = csvImportNormalizeDate($data[$dateField]) ?? '';
    }

    $name = (string) ($data['staff_name'] ?? '');
    $kana = (string) ($data['kana_name'] ?? '');
    if ($name === '' && $kana === '') {
        continue;
    }

    $key = csvImportNormalizePersonKey($kana) ?: csvImportNormalizePersonKey($name);
    if ($key === '') {
        continue;
    }

    $statusRaw = trim((string) ($data['status'] ?? ''));
    if ($statusRaw === '支援') {
        $data['status'] = '支援中';
    } elseif ($statusRaw === '終了') {
        $data['status'] = '終了';
    } else {
        $data['status'] = $statusRaw !== '' ? $statusRaw : '支援中';
    }

    $csvRows[$key] = $data;
}

try {
    if ($dumpPath !== '' && is_readable($dumpPath)) {
        $dbSource = 'SQL dump: ' . $dumpPath;
        $dbList = dumpLoadSupportRows($dumpPath);
    } else {
        $dbSource = 'DB (local/production via config.php)';
        $dbList = dbLoadSupportRows($specificSkillsRoot);
    }
} catch (Throwable $e) {
    if ($dumpPath === '') {
        $fallback = dirname($csvPath) . '/specific_skills_20260818.dmp';
        if (is_readable($fallback)) {
            $dumpPath = $fallback;
            $dbSource = 'SQL dump: ' . $dumpPath;
            $dbList = dumpLoadSupportRows($dumpPath);
        } else {
            fwrite(STDERR, 'DB/dump load failed: ' . $e->getMessage() . "\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, 'Dump load failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

$dbRows = [];
foreach ($dbList as $row) {
    $name = (string) ($row['staff_name'] ?? '');
    $kana = (string) ($row['kana_name'] ?? '');
    $key = csvImportNormalizePersonKey($kana) ?: csvImportNormalizePersonKey($name);
    if ($key === '') {
        continue;
    }
    $dbRows[$key] = $row;
}

$onlyInCsv = array_diff(array_keys($csvRows), array_keys($dbRows));
$onlyInDb = array_diff(array_keys($dbRows), array_keys($csvRows));
$common = array_intersect(array_keys($csvRows), array_keys($dbRows));

$mismatches = [];
foreach ($common as $key) {
    $csv = $csvRows[$key];
    $db = $dbRows[$key];
    $diffs = [];
    foreach ($compareKeys as $field) {
        $csvVal = trim((string) ($csv[$field] ?? ''));
        $dbVal = trim((string) ($db[$field] ?? ''));
        if ($csvVal === '' && $dbVal === '') {
            continue;
        }
        if ($csvVal !== $dbVal) {
            // Ignore trailing whitespace-only differences in free text fields.
            if (in_array($field, ['workplace', 'company_name', 'notes'], true)
                && rtrim($csvVal) === rtrim($dbVal)
            ) {
                continue;
            }
            $diffs[$field] = ['csv' => $csvVal, 'db' => $dbVal];
        }
    }
    $csvStatus = (string) ($csv['status'] ?? '');
    $dbStatus = (string) ($db['status'] ?? '');
    if ($csvStatus !== $dbStatus) {
        $diffs['status'] = ['csv' => $csvStatus, 'db' => $dbStatus];
    }
    if ($diffs !== []) {
        $mismatches[$key] = [
            'name' => (string) ($csv['staff_name'] ?? $db['staff_name'] ?? $key),
            'diffs' => $diffs,
        ];
    }
}

echo "=== 支援管理 CSV vs ポータル 比較 ===\n";
echo "比較元: {$dbSource}\n";
echo 'CSV データ行: ' . count($csvRows) . "\n";
echo 'ポータル支援管理行: ' . count($dbRows) . "\n";
echo 'CSV にのみ存在: ' . count($onlyInCsv) . "\n";
echo 'ポータルのみ存在: ' . count($onlyInDb) . "\n";
echo '共通: ' . count($common) . "\n";
echo '値の相違あり: ' . count($mismatches) . "\n";

if ($unmappedHeaders !== []) {
    echo "\nCSV列（ポータル未マッピング）:\n";
    foreach ($unmappedHeaders as $h) {
        echo "  - {$h}\n";
    }
}

if ($onlyInCsv !== []) {
    echo "\n--- CSV のみ（ポータルに未登録）---\n";
    foreach ($onlyInCsv as $key) {
        echo '  ' . ($csvRows[$key]['staff_name'] ?? $key) . "\n";
    }
}

if ($onlyInDb !== []) {
    echo "\n--- ポータルのみ（CSV に未記載）---\n";
    foreach (array_slice($onlyInDb, 0, 20) as $key) {
        echo '  ' . ($dbRows[$key]['staff_name'] ?? $key) . "\n";
    }
    if (count($onlyInDb) > 20) {
        echo '  ... 他 ' . (count($onlyInDb) - 20) . " 件\n";
    }
}

if ($mismatches !== []) {
    echo "\n--- 値の相違（最大20件）---\n";
    $shown = 0;
    foreach ($mismatches as $info) {
        if ($shown >= 20) {
            break;
        }
        echo "\n【" . $info['name'] . "】\n";
        foreach ($info['diffs'] as $field => $vals) {
            $csvShow = $vals['csv'] === '' ? '(空)' : mb_substr($vals['csv'], 0, 60);
            $dbShow = $vals['db'] === '' ? '(空)' : mb_substr($vals['db'], 0, 60);
            echo "  {$field}: CSV={$csvShow} | DB={$dbShow}\n";
        }
        $shown++;
    }
    if (count($mismatches) > 20) {
        echo "\n... 他 " . (count($mismatches) - 20) . " 件に相違あり\n";
    }
}

if ($onlyInCsv === [] && $onlyInDb === [] && $mismatches === []) {
    echo "\n結果: 件数・主要項目とも一致しています。\n";
} elseif (count($onlyInCsv) === 0 && count($onlyInDb) === 0) {
    echo "\n結果: 対象者の一覧は一致。一部フィールドに差異があります（上記参照）。\n";
} else {
    echo "\n結果: 一覧または件数に差異があります。\n";
}
