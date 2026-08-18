<?php

return [

    /*
    |--------------------------------------------------------------------------
    | サテライトオフィス SSO（GET/POST 簡易連携）受信設定
    |--------------------------------------------------------------------------
    |
    | サテライト管理画面「連携設定の追加」（SAML2.0 以外）で指定する
    | 連携先 URL・パラメータ名・署名方式と一致させてください。
    |
    */

    'enabled' => (bool) env('SATERAITO_SSO_ENABLED', false),

    'secret' => env('SATERAITO_SSO_SECRET'),

    /**
     * サテライトから渡されるクエリ／POST パラメータ名
     */
    'params' => [
        'email' => env('SATERAITO_SSO_EMAIL_PARAM', 'mail'),
        'timestamp' => env('SATERAITO_SSO_TIMESTAMP_PARAM', 'time'),
        'signature' => env('SATERAITO_SSO_SIGNATURE_PARAM', 'sign'),
        'redirect' => env('SATERAITO_SSO_REDIRECT_PARAM', 'redirect'),
    ],

    /**
     * 署名検証（共通鍵 + フィールド連結。サテライト側の設定に合わせる）
     *
     * algorithm: md5 | sha1 | sha256 | hmac_sha256
     * secret_position:
     |   prefix  … secret + fields
     |   suffix  … fields + secret
     |   none    … fields のみ（secret は hmac_sha256 時のみ使用）
     | fields: 署名に含める論理キー（email, timestamp など。順序重要）
     */
    'signature' => [
        'enabled' => (bool) env('SATERAITO_SSO_SIGNATURE_ENABLED', true),
        'algorithm' => env('SATERAITO_SSO_SIGNATURE_ALGORITHM', 'md5'),
        'secret_position' => env('SATERAITO_SSO_SECRET_POSITION', 'prefix'),
        'fields' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SATERAITO_SSO_SIGNATURE_FIELDS', 'email,timestamp'))
        ))),
    ],

    /** @var list<string> ログインを許可するメールドメイン（空なら制限なし） */
    'allowed_email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'SATERAITO_SSO_ALLOWED_EMAIL_DOMAINS',
            'careearth.info,careearth.net'
        ))
    ))),

    /** タイムスタンプ許容秒数（0 で有効期限チェックなし） */
    'max_age_seconds' => (int) env('SATERAITO_SSO_MAX_AGE_SECONDS', 120),

    /** ログイン成功後の既定遷移先（redirect パラメータが無い場合） */
    'default_redirect' => env('SATERAITO_SSO_DEFAULT_REDIRECT', '/dashboard'),

    /**
     * セキュリティーブラウザ／マイページから踏むサテライト側 SSO 入口 URL。
     * 未設定時は sso_domain + sso_id から組み立てます。
     *
     * 例: https://sateraito-apps-sso.appspot.com/a/careearth.info/sso/general?ssoid=CEEMPLOYEE01
     */
    'entry_url' => env('SATERAITO_SSO_ENTRY_URL'),

    'sso_domain' => env('SATERAITO_SSO_DOMAIN', 'careearth.info'),

    'sso_id' => env('SATERAITO_SSO_ID'),

];
