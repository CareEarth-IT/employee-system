<?php
declare(strict_types=1);

/**
 * Apps Script（Code.gs / appsscript.json）から移植したアプリ設定
 */
$webAppUrl = getenv('FINANCE_HR_WEB_APP_URL');
$chatMain = getenv('FINANCE_HR_CHAT_WEBHOOK_URL');
$chatHr = getenv('FINANCE_HR_CHAT_WEBHOOK_URL_HR');

return [
    /** appsscript.json: timeZone */
    'timezone' => 'Asia/Tokyo',
    'mb_internal_encoding' => 'UTF-8',

    /**
     * appsscript.json webapp.access = DOMAIN の相当
     * 許可するメールドメイン（ローカルデモ用に example.com も含む）
     */
    'allowed_email_domains' => [
        'careearth.info',
        'example.com',
    ],

    /**
     * GAS の oauthScopes は PHP では不要（参考用）
     * - script.external_request → Chat Webhook (cURL)
     * - spreadsheets / drive → MySQL + uploads/
     * - userinfo / directory → users テーブルのプロフィール
     */
    'gas_oauth_scopes_note' => [
        'https://www.googleapis.com/auth/script.external_request',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'https://www.googleapis.com/auth/admin.directory.user.readonly',
        'https://www.googleapis.com/auth/directory.readonly',
    ],

    'default_company' => 'CareEarth株式会社',
    'web_app_url' => ($webAppUrl !== false && $webAppUrl !== '')
        ? rtrim($webAppUrl, '/')
        : 'http://employee.local/finance-hr',
    'approver_target_email' => 'takuya_nishi@careearth.info',

    /**
     * 担当者画面をメールで許可（部署判定に加え）。FINANCE_HR_ADMIN_EMAILS で上書き可。
     *
     * @var list<string>
     */
    'admin_emails' => (static function (): array {
        $raw = getenv('FINANCE_HR_ADMIN_EMAILS');
        if (is_string($raw) && trim($raw) !== '') {
            $emails = array_values(array_filter(array_map(
                static fn (string $v): string => strtolower(trim($v)),
                explode(',', $raw),
            )));
            if ($emails !== []) {
                return $emails;
            }
        }

        return ['ginga_fukui@careearth.info'];
    })(),

    'google_chat_webhook_url' => ($chatMain !== false && $chatMain !== '')
        ? $chatMain
        : 'https://chat.googleapis.com/v1/spaces/AAQAJSDTSa0/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=KYJ7ay2weuUCbYS-Z2Rt5nCvdAvw5rBcJ7mq9U9t9Ek',

    'google_chat_webhook_url_hr' => ($chatHr !== false && $chatHr !== '')
        ? $chatHr
        : 'https://chat.googleapis.com/v1/spaces/AAQAPnnmarc/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=dvuu_PCk7u0f9iWkCJSOCWCy5KZD4HqSic7YN70QIWk',

    'google_chat_webhook_url_is' => (getenv('FINANCE_HR_CHAT_WEBHOOK_URL_IS') !== false && getenv('FINANCE_HR_CHAT_WEBHOOK_URL_IS') !== '')
        ? (string) getenv('FINANCE_HR_CHAT_WEBHOOK_URL_IS')
        : '',

    /**
     * 問い合わせカテゴリ（同一 DB・sheet_key で区別）
     * finance → sheet_key=main（既存互換）
     */
    'inquiry_categories' => [
        'hr' => [
            'key' => 'hr',
            'sheet_key' => 'hr',
            'label' => '人事',
            'types' => [
                '在留カード更新',
                'スマートHRについて',
                '入社・退社手続き',
                'キャリア進路相談',
                '所属移動の手続き',
            ],
        ],
        'finance' => [
            'key' => 'finance',
            'sheet_key' => 'main',
            'label' => '経理',
            'types' => [
                '経費/交通費',
                '請求書/楽楽精算',
                '会社の在籍証明書',
                '給与について',
                '自分の所得証明書',
                '自分の年末調整',
            ],
        ],
        'is' => [
            'key' => 'is',
            'sheet_key' => 'is',
            'label' => '情シス',
            'types' => [
                '楽楽精算ログインまで => 林',
                'Google関連 => 増井',
                'Airtable => カデアー',
                '端末関連 => 林',
                'インターネット関連 => 中元',
                '開発（各部開発等） => 中元',
                '電話やスマートフォン => 林',
            ],
        ],
    ],

    /** @deprecated inquiry_categories.hr.types を利用 */
    'hr_inquiry_types' => [
        '在留カード更新',
        'スマートHRについて',
        '入社・退社手続き',
        'キャリア進路相談',
        '所属移動の手続き',
    ],

    'onboarding_doc_type' => '',

    /**
     * 担当部署グループ（個人メールではなく所属キーワードで判定）
     * SSO の department 例: 「経理部 / 総務課」「経理部 / 経理課」
     * match_mode=all のとき department_keywords はすべて含む必要がある
     *
     * 本番の上書きは DB app_settings（権限設定画面）から可能
     */
    'department_groups' => [
        'keiri_ka' => [
            'id' => 'keiri_ka',
            'label' => '経理課',
            'department_keywords' => ['経理部', '経理課'],
            'match_mode' => 'all',
        ],
        'soumu_ka' => [
            'id' => 'soumu_ka',
            'label' => '総務課',
            'department_keywords' => ['経理部', '総務課'],
            'match_mode' => 'all',
        ],
        'hr' => [
            'id' => 'hr',
            'label' => '人事課',
            'department_keywords' => ['人事課'],
            'match_mode' => 'any',
        ],
        'is' => [
            'id' => 'is',
            'label' => '情シス',
            'department_keywords' => ['情報システム'],
            'match_mode' => 'any',
        ],
    ],

    /**
     * お問い合わせ分類 × 部署グループ → edit | view | none
     */
    'type_permission_matrix' => [
        '経費/交通費' => ['keiri_ka' => 'edit'],
        '請求書/楽楽精算' => ['keiri_ka' => 'edit'],
        '会社の在籍証明書' => ['soumu_ka' => 'edit'],
        '給与について' => ['keiri_ka' => 'edit'],
        '自分の所得証明書' => ['keiri_ka' => 'edit'],
        '自分の年末調整' => ['keiri_ka' => 'edit'],

        '在留カード更新' => ['hr' => 'edit'],
        'スマートHRについて' => ['hr' => 'edit'],
        '入社・退社手続き' => ['hr' => 'edit'],
        'キャリア進路相談' => ['hr' => 'edit'],
        '所属移動の手続き' => ['hr' => 'edit'],

        '楽楽精算ログインまで' => ['is' => 'edit'],
        'Google関連' => ['is' => 'edit'],
        'Airtable' => ['is' => 'edit'],
        '端末関連' => ['is' => 'edit'],
        'インターネット関連' => ['is' => 'edit'],
        '開発（各部開発等）' => ['is' => 'edit'],
        '電話やスマートフォン' => ['is' => 'edit'],
    ],
];
