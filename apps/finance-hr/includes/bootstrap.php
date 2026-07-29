<?php
declare(strict_types=1);

/**
 * 社員ポータル .env から不足分の環境変数を補完（ローカル Apache 用）
 * Cloud Run では既に getenv で渡されるためスキップされる。
 */
function finance_hr_hydrate_env_from_portal(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $needed = [
        'APP_KEY',
        'FINANCE_HR_SSO_SECRET',
        'FINANCE_HR_DB_HOST',
        'FINANCE_HR_DB_PORT',
        'FINANCE_HR_DB_DATABASE',
        'FINANCE_HR_DB_USERNAME',
        'FINANCE_HR_DB_PASSWORD',
        'FINANCE_HR_DB_SOCKET',
        'FINANCE_HR_WEB_APP_URL',
        'FINANCE_HR_CHAT_WEBHOOK_URL',
        'FINANCE_HR_CHAT_WEBHOOK_URL_HR',
        // ローカルでは Laravel の DB_* を流用可能
        'DB_HOST',
        'DB_PORT',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    $missing = false;
    foreach ($needed as $key) {
        $val = getenv($key);
        if ($val === false || $val === '') {
            $missing = true;
            break;
        }
    }
    if (! $missing) {
        return;
    }

    $envPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'.env';
    if (! is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (str_ends_with($value, $quote)) {
                $value = substr($value, 1, -1);
            }
        }
        $existing = getenv($name);
        if (($existing === false || $existing === '') && $value !== '') {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
        }
    }

    // FINANCE_HR_DB_* 未設定時は Laravel DB_* を流用（DB名のみ finance_hr）
    if ((getenv('FINANCE_HR_DB_HOST') === false || getenv('FINANCE_HR_DB_HOST') === '')
        && getenv('DB_HOST')) {
        putenv('FINANCE_HR_DB_HOST='.getenv('DB_HOST'));
    }
    if ((getenv('FINANCE_HR_DB_PORT') === false || getenv('FINANCE_HR_DB_PORT') === '')
        && getenv('DB_PORT')) {
        putenv('FINANCE_HR_DB_PORT='.getenv('DB_PORT'));
    }
    if ((getenv('FINANCE_HR_DB_USERNAME') === false || getenv('FINANCE_HR_DB_USERNAME') === '')
        && getenv('DB_USERNAME')) {
        putenv('FINANCE_HR_DB_USERNAME='.getenv('DB_USERNAME'));
    }
    if ((getenv('FINANCE_HR_DB_PASSWORD') === false || getenv('FINANCE_HR_DB_PASSWORD') === '')
        && getenv('DB_PASSWORD') !== false) {
        putenv('FINANCE_HR_DB_PASSWORD='.getenv('DB_PASSWORD'));
    }
    if (getenv('FINANCE_HR_DB_DATABASE') === false || getenv('FINANCE_HR_DB_DATABASE') === '') {
        putenv('FINANCE_HR_DB_DATABASE=finance_hr');
    }
}

finance_hr_hydrate_env_from_portal();

/**
 * アプリ共通初期化（Apps Script appsscript.json 相当）
 */
$appCfg = require __DIR__ . '/../config/app.php';

date_default_timezone_set((string) ($appCfg['timezone'] ?? 'Asia/Tokyo'));

if (!empty($appCfg['mb_internal_encoding'])) {
    mb_internal_encoding((string) $appCfg['mb_internal_encoding']);
}
