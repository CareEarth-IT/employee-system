<?php

return [

    /*
    |--------------------------------------------------------------------------
    | サテライト経由の入場制限
    |--------------------------------------------------------------------------
    |
    | true のとき、未ログインユーザーはサテライト SSO または許可 Referer 経由で
    | 「入場許可」セッションを得るまで /login および保護ページへ入れません。
    |
    */

    'require_sateraito' => (bool) env('PORTAL_REQUIRE_SATERAITO_ENTRY', false),

    /** @var list<string> /login 直接アクセスを許可する Referer ホスト（サテライト SSO ゲートウェイ等） */
    'allowed_referer_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'PORTAL_SATERAITO_REFERER_HOSTS',
            'sateraito-apps-sso.appspot.com,sso.sateraito.jp'
        ))
    ))),

    /** 入場許可セッションの有効秒数（0 で Laravel セッション寿命まで） */
    'grant_ttl_seconds' => (int) env('PORTAL_ENTRY_GRANT_TTL_SECONDS', 900),

];
