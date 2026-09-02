<?php

namespace App\Support;

class RegistrySectionOptions
{
    /** @var list<string> 社員登録フォームの課（マスタ一覧） */
    public const ALL_OPTIONS = [
        '人事課',
        '業務課',
        '情報管理課',
        '労務法務課',
        '営業1課',
        '営業2課',
        '営業3課',
        '営業4課',
        '営業5課',
        'SS課',
        '人材育成課',
        '現場社員',
        '大阪グローバル事業部',
        'GR-S_大阪',
        'GR-S1課_大阪',
        'GR-S2課_大阪',
        'GR-S3課_大阪',
        'GR-M_大阪',
        'GR-O_大阪',
        'GR-C_大阪',
        '東京支店',
        'GR-S_東京',
        'GR-M_東京',
        'GR-O_東京',
        '名古屋支店',
        'GR-S_名古屋',
        'GR-M_名古屋',
        'GR-O_名古屋',
        'GR-C_名古屋',
        '福岡支店',
        'ベトナム支店',
        'GR-C_東京',
        'GR-C総務課_東京',
        'GR-C教育課_東京',
        'GR-S1課_東京',
        'GR-S2課_東京',
        'GR-S3課_東京',
        'GR-M1課_東京',
        'GR-M2課_東京',
        '名古屋営業部',
        'GR-S1課_名古屋',
        'GR-S2課_名古屋',
        '名古屋グローバル事業部',
        'GR-C総務課_名古屋',
        'GR-C教育課_名古屋',
        'GR-M1課_名古屋',
        'GR-セールス3課_大阪',
        'GR-C_福岡',
        'GR-S_福岡',
        'GR-O_福岡',
        'GR-C 教育課_福岡',
        'GR-C 総務課_福岡',
        '経理課',
        '総務課',
        'GR-S1課_福岡',
        'GR-S2課_福岡',
        'GR-S3課_福岡',
        'GR-O 送迎課_福岡',
        '物流課',
        '店舗運営課',
        'GR-O CS課_福岡',
        'GR-総務課_大阪',
        'GR-教育課_大阪',
        '人材開発・人材育成課',
        '広報・マーケティング課',
        'GR-M1課_大阪',
        'GR-M2課_大阪',
        'GR-O 送迎課_大阪',
        'GR-O CS課_大阪',
        '名古屋',
        '庶務課',
        'GR-O CS課_名古屋',
        'GR-O CS課_東京',
        'GR-M3課_東京',
        'GR-S_ベトナム',
        '管理部業務課',
        'GR-M_福岡',
        'Food Sales部',
        'Food GA部',
        'Food Logistics部',
        'Food Retail部',
        'GR-M2課_名古屋',
        '営業課_福岡',
        'SS課_福岡',
        '営業課_名古屋',
        'SS課_名古屋',
    ];

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        $departmentOptions = RegistryDepartmentOptions::options();
        $seen = [];
        $options = [];

        foreach (self::ALL_OPTIONS as $option) {
            if (RegistryTeamOptions::isTeam($option)) {
                continue;
            }

            if (in_array($option, $departmentOptions, true)) {
                continue;
            }

            if (isset($seen[$option])) {
                continue;
            }

            $seen[$option] = true;
            $options[] = $option;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function forSelect(?string $current = null): array
    {
        $options = self::options();

        if ($current === null || $current === '' || in_array($current, $options, true)) {
            return $options;
        }

        return [...$options, $current];
    }
}
