<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 部署別社内サイト（employee 経由プロキシ）
    |--------------------------------------------------------------------------
    |
    | 社員共通（common）は社内サイトを持たず、リファラー制限もありません。
    | internal_url が空の部署はリンク・プロキシを有効化しません。
    | リンクは employee 上のプロキシ URL 経由。
    | real-estate は Cloud Run IAM の代わりに EMPLOYEE_PORTAL_PROXY_SECRET（アプリ側認証）を使用可能。
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
