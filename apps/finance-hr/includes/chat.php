<?php
declare(strict_types=1);

require_once __DIR__ . '/staff.php';

function chat_webhook_url_for_type(string $type): string
{
    $cfg = app_config();
    $category = category_key_for_type($type);
    if ($category === 'hr') {
        return trim((string) ($cfg['google_chat_webhook_url_hr'] ?? ''));
    }
    if ($category === 'is') {
        $isUrl = trim((string) ($cfg['google_chat_webhook_url_is'] ?? ''));
        if ($isUrl !== '') {
            return $isUrl;
        }
        // 未設定時は経理側 Webhook にフォールバック
        return trim((string) ($cfg['google_chat_webhook_url'] ?? ''));
    }
    return trim((string) ($cfg['google_chat_webhook_url'] ?? ''));
}

function chat_failure_hint(int $status, string $body): string
{
    if ($status === 0) {
        return 'Webhook 送信に失敗しました。URL とネットワークを確認してください。';
    }
    if ($status === 404) {
        return 'Webhook URL が無効です。Google Chat で新しい Incoming Webhook を作成してください。';
    }
    if ($status === 403) {
        return 'Webhook のアクセスが拒否されました。URL を再取得してください。';
    }
    if ($status >= 400) {
        return mb_substr($body, 0, 200);
    }
    return mb_substr($body, 0, 200) ?: '不明なエラー';
}

function build_chat_text(array $profile, array $formData, int $rowNum, string $sheetKey): string
{
    $now = date('Y/m/d H:i');
    $lines = [
        '<users/all>',
        '📨 新しいお問い合わせが届きました',
        '行番号: ' . $rowNum . '  |  送信日時: ' . $now,
        '',
        '氏名: ' . ($profile['fullName'] ?? '—'),
        '社員ID: ' . ($profile['employeeId'] ?? '—'),
        '所属: ' . ($profile['company'] ?? '—') . ' / ' . ($profile['department'] ?? '—'),
        'メール: ' . ($profile['email'] ?? '—'),
        '',
        '質問内容: ' . ($formData['type'] ?? '—'),
        'タイトル: ' . ($formData['title'] ?? '—'),
        "内容:\n" . ($formData['body'] ?? '—'),
    ];

    if (!empty($formData['attachmentUrls'])) {
        $lines[] = '';
        $lines[] = "添付ファイル:\n" . $formData['attachmentUrls'];
    }

    $text = implode("\n", $lines);
    if ($sheetKey === 'hr') {
        $text = "【人事】\n" . $text;
    } elseif ($sheetKey === 'is') {
        $text = "【情シス】\n" . $text;
    } elseif ($sheetKey === 'main') {
        $text = "【経理】\n" . $text;
    }
    return $text;
}

function post_chat_webhook(string $webhookUrl, array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return ['ok' => false, 'status' => 0, 'body' => 'JSON encode failed'];
    }

    $ch = curl_init($webhookUrl);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => 'curl_init failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $body = (string) curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'status' => 0, 'body' => $error ?: 'curl error'];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body,
    ];
}

/**
 * @return array{ok:bool,status:int,body:string}
 */
function send_chat_notification(array $profile, array $formData, int $rowNum, string $sheetKey = 'main'): array
{
    $webhookUrl = chat_webhook_url_for_type((string) ($formData['type'] ?? ''));
    if ($webhookUrl === '' || str_contains($webhookUrl, 'XXXXXX')) {
        return ['ok' => false, 'status' => 0, 'body' => 'Webhook URL 未設定'];
    }

    $text = build_chat_text($profile, $formData, $rowNum, $sheetKey);
    $adminUrl = admin_page_url($rowNum, $sheetKey);

    $payload = ['text' => $text];
    if ($adminUrl !== '') {
        $payload['cardsV2'] = [[
            'cardId' => 'inquiry-admin-'.$rowNum.'-'.time(),
            'card' => [
                'header' => [
                    'title' => 'お問い合わせ #'.$rowNum,
                    'subtitle' => '担当者は「確認」から処理してください',
                ],
                'sections' => [[
                    'widgets' => [[
                        'buttonList' => [
                            'buttons' => [[
                                'text' => '確認',
                                'onClick' => [
                                    'openLink' => ['url' => $adminUrl],
                                ],
                            ]],
                        ],
                    ]],
                ]],
            ],
        ]];
    }

    $result = post_chat_webhook($webhookUrl, $payload);
    if ($result['ok']) {
        return $result;
    }

    if (! empty($payload['cardsV2']) && $adminUrl !== '') {
        $fallback = ['text' => $text."\n\n▶ 管理画面: ".$adminUrl];
        $result = post_chat_webhook($webhookUrl, $fallback);
    }

    return $result;
}
