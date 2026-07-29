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
                '（社員の）在籍証明書について',
                '（社員の）ビザ更新について',
                '（社員の）入社書類提出 (身元保証書・入社契約書・源泉徴収票・アルバイト雇用契約書)',
                '（社員の）SmartHR操作相談について',
            ],
        ],
        'finance' => [
            'key' => 'finance',
            'sheet_key' => 'main',
            'label' => '経理',
            'types' => [
                '（社員の）給与について',
                '（社員の）経費精算について',
                '（社員の）勤怠管理について',
                '（社員の）交通費について',
                '（社員の）育児休業について',
                '（社員の）傷病手当について',
                '（社員の）源泉徴収票について',
                '（社員の）就業証明書について',
                '会社情報について',
                '（社員の）住民税について',
                '（社員の）健康保険・雇用保険について',
                '（派遣スタッフの）源泉徴収票について',
                '楽楽精算の操作・設定について',
                '社員以外の経費の相談について',
                '（事業部から）経理課への問い合わせ',
                '（事業部から）総務課への問い合わせ',
            ],
        ],
        'is' => [
            'key' => 'is',
            'sheet_key' => 'is',
            'label' => '情シス',
            'types' => [
                '派遣の開発システム　⇒　カデアー',
                '派遣以外の開発システム　⇒　増井',
                'ソフトやGoogleサービスについて　⇒　増井',
                '新たな依頼（組換系・中央のスケジュール予約セット）　⇒　中元',
                'PC, Wifi, スマホ , ....　⇒　林、南谷',
                'その他　⇒　中元',
                'Airtable について問い合わせ　⇒　カデアー',
            ],
        ],
    ],

    /** @deprecated inquiry_categories.hr.types を利用 */
    'hr_inquiry_types' => [
        '（社員の）在籍証明書について',
        '（社員の）ビザ更新について',
        '（社員の）入社書類提出 (身元保証書・入社契約書・源泉徴収票・アルバイト雇用契約書)',
        '（社員の）SmartHR操作相談について',
    ],

    'onboarding_doc_type' =>
        '（社員の）入社書類提出 (身元保証書・入社契約書・源泉徴収票・アルバイト雇用契約書)',

    /**
     * 担当部署グループ（個人メールではなく所属キーワードで判定）
     * SSO の department 例: 「経理部 / 総務課」
     */
    'department_groups' => [
        'keiri' => [
            'id' => 'keiri',
            'label' => '経理',
            'department_keywords' => ['経理課'],
        ],
        'soumu' => [
            'id' => 'soumu',
            'label' => '総務',
            'department_keywords' => ['総務課'],
        ],
        'hr' => [
            'id' => 'hr',
            'label' => '人事',
            'department_keywords' => ['人事課'],
        ],
        'is' => [
            'id' => 'is',
            'label' => '情シス',
            'department_keywords' => ['情報システム'],
        ],
    ],

    /**
     * お問い合わせ分類 × 部署グループ → edit | view | none
     * 旧個人マトリクスを経理 / 総務 / 人事 / 情シスに読み替え
     */
    'type_permission_matrix' => [
        '（社員の）給与について' => ['soumu' => 'edit'],
        '（社員の）経費精算について' => ['keiri' => 'edit'],
        '（社員の）勤怠管理について' => ['soumu' => 'edit'],
        '（社員の）交通費について' => ['keiri' => 'edit'],
        '（社員の）育児休業について' => ['soumu' => 'edit'],
        '（社員の）傷病手当について' => ['soumu' => 'edit'],
        '（社員の）源泉徴収票について' => ['soumu' => 'edit'],
        '（社員の）就業証明書について' => ['soumu' => 'edit'],
        '会社情報について' => ['keiri' => 'edit', 'soumu' => 'edit'],
        '（社員の）住民税について' => ['soumu' => 'edit'],
        '（社員の）健康保険・雇用保険について' => ['soumu' => 'edit'],
        '（派遣スタッフの）源泉徴収票について' => ['keiri' => 'edit'],
        '楽楽精算の操作・設定について' => ['keiri' => 'edit'],
        '社員以外の経費の相談について' => ['keiri' => 'edit'],
        '（事業部から）経理課への問い合わせ' => ['keiri' => 'edit'],
        '（事業部から）総務課への問い合わせ' => ['soumu' => 'edit'],

        '（社員の）在籍証明書について' => ['hr' => 'edit'],
        '（社員の）ビザ更新について' => ['hr' => 'edit'],
        '（社員の）入社書類提出 (身元保証書・入社契約書・源泉徴収票・アルバイト雇用契約書)' => ['hr' => 'edit'],
        '（社員の）SmartHR操作相談について' => ['hr' => 'edit'],

        '派遣の開発システム　⇒　カデアー' => ['is' => 'edit'],
        '派遣以外の開発システム　⇒　増井' => ['is' => 'edit'],
        'ソフトやGoogleサービスについて　⇒　増井' => ['is' => 'edit'],
        '新たな依頼（組換系・中央のスケジュール予約セット）　⇒　中元' => ['is' => 'edit'],
        'PC, Wifi, スマホ , ....　⇒　林、南谷' => ['is' => 'edit'],
        'その他　⇒　中元' => ['is' => 'edit'],
        'Airtable について問い合わせ　⇒　カデアー' => ['is' => 'edit'],
    ],
];
