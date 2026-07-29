<?php

return [

    'list_months' => (int) env('DEVELOPMENT_REQUEST_LIST_MONTHS', 3),

    'request_number_start' => (int) env('DEVELOPMENT_REQUEST_NUMBER_START', 10001),

    /**
     * Google Chat Incoming Webhook（空なら通知しない）
     * GAS 時代の開発依頼スペースと同じ URL を設定する。
     */
    'chat_webhook_url' => env('DEVELOPMENT_REQUEST_CHAT_WEBHOOK_URL', ''),

    /**
     * 詳細の編集権限：所属「部」に含まれるキーワード（デフォルトは情報システム部）。
     * DEVELOPMENT_REQUEST_EDITOR_DEPARTMENT_KEYWORDS で上書き（カンマ区切り）。
     *
     * @var list<string>
     */
    'editor_department_keywords' => array_values(array_filter(array_map(
        static fn (string $keyword): string => trim($keyword),
        explode(',', (string) env(
            'DEVELOPMENT_REQUEST_EDITOR_DEPARTMENT_KEYWORDS',
            '情報システム',
        )),
    ))),

    /**
     * 詳細の閲覧のみ：所属に含まれるキーワード（デフォルトは役員）。
     * 編集権限者は自動的に閲覧可。
     * DEVELOPMENT_REQUEST_VIEWER_DEPARTMENT_KEYWORDS で上書き（カンマ区切り）。
     *
     * @var list<string>
     */
    'viewer_department_keywords' => array_values(array_filter(array_map(
        static fn (string $keyword): string => trim($keyword),
        explode(',', (string) env(
            'DEVELOPMENT_REQUEST_VIEWER_DEPARTMENT_KEYWORDS',
            '役員',
        )),
    ))),

];
