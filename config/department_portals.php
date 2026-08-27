<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 部署別社内サイト（employee 経由プロキシ）
    |--------------------------------------------------------------------------
    |
    | キー（例: specified-skills）は DashboardTab::TABS の key と一致させること。
    | 検証: php artisan department-portals:check
    |
    | 社員共通（common）は社内サイトを持たず、リファラー制限もありません。
    | internal_url が空の部署はリンク・プロキシを有効化しません。
    | リンクは employee 上のプロキシ URL 経由。
    |
    | 認証（upstream への到達）:
    |   - 特定技能等: Cloud Run Identity Token（本番デフォルト）
    |   - 不動産: Identity Token または EMPLOYEE_PORTAL_PROXY_SECRET
    | 詳細: docs/architecture.md
    |
    */

    'real-estate' => [
        'label' => '不動産社内サイト',
        'internal_url' => env('REAL_ESTATE_PORTAL_INTERNAL_URL', 'https://real-estate-portal-3hlnt2gvnq-an.a.run.app'),
        'proxy_path' => 'realestate-portal',
        'entry_path' => 'home',
        'proxy_secret' => env('EMPLOYEE_PORTAL_PROXY_SECRET'),
        'use_identity_token' => env('REAL_ESTATE_PORTAL_USE_IDENTITY_TOKEN'),
    ],

    'dispatch' => [
        'label' => '派遣事業社内サイト',
        'internal_url' => env('DISPATCH_PORTAL_INTERNAL_URL'),
        'proxy_path' => 'dispatch-portal',
        'entry_path' => '',
        'use_identity_token' => env('DISPATCH_PORTAL_USE_IDENTITY_TOKEN'),
    ],

    'specified-skills' => [
        'label' => '特定技能社内サイト',
        'internal_url' => env('SPECIFIED_SKILLS_PORTAL_INTERNAL_URL'),
        'proxy_path' => 'specified-skills-portal',
        'entry_path' => '',
        'use_identity_token' => env('SPECIFIED_SKILLS_PORTAL_USE_IDENTITY_TOKEN'),
    ],

    'food' => [
        'label' => '食品社内サイト',
        'internal_url' => env('FOOD_PORTAL_INTERNAL_URL'),
        'proxy_path' => 'food-portal',
        'entry_path' => '',
        'use_identity_token' => env('FOOD_PORTAL_USE_IDENTITY_TOKEN'),
    ],

    'telecom' => [
        'label' => '通信社内サイト',
        'internal_url' => env('TELECOM_PORTAL_INTERNAL_URL'),
        'proxy_path' => 'telecom-portal',
        'entry_path' => '',
        'use_identity_token' => env('TELECOM_PORTAL_USE_IDENTITY_TOKEN'),
    ],

    'beauty' => [
        'label' => '美容社内サイト',
        'internal_url' => env('BEAUTY_PORTAL_INTERNAL_URL'),
        'proxy_path' => 'beauty-portal',
        'entry_path' => '',
        'use_identity_token' => env('BEAUTY_PORTAL_USE_IDENTITY_TOKEN'),
    ],

];
