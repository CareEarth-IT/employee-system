<?php

namespace App\Models\Concerns\EquipmentPurchase;

trait HasApproverEmails
{
    /**
     * 社内備品・3万円以上の承認者メール（複数可）。
     * EQUIPMENT_PURCHASE_INTERNAL_OVER_30K_APPROVER_EMAILS で上書き（カンマ区切り）。
     *
     * @return list<string>
     */
    public static function internalOver30kApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_INTERNAL_OVER_30K_APPROVER_EMAILS',
            [self::INTERNAL_OVER_30K_APPROVER_EMAIL],
        );
    }

    /**
     * 情報システム部申請の承認者メール（複数可）。
     * EQUIPMENT_PURCHASE_INFORMATION_SYSTEMS_APPROVER_EMAILS で上書き（カンマ区切り）。
     *
     * @return list<string>
     */
    public static function informationSystemsApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_INFORMATION_SYSTEMS_APPROVER_EMAILS',
            [self::INFORMATION_SYSTEMS_APPROVER_EMAIL],
        );
    }

    /**
     * 上長以上の備品申請を全部署で承認できるアカウントのメール（複数可）。
     * EQUIPMENT_PURCHASE_GLOBAL_MANAGER_APPROVER_EMAILS で上書き（カンマ区切り）。
     *
     * @return list<string>
     */
    public static function globalManagerApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_GLOBAL_MANAGER_APPROVER_EMAILS',
            [self::GLOBAL_MANAGER_APPROVER_EMAIL],
        );
    }

    /**
     * 食品・桃谷店・3万円以上の承認者（ティエンさん）。
     * EQUIPMENT_PURCHASE_FOOD_MOMOTANI_OVER_30K_APPROVER_EMAILS で上書き可。
     *
     * @return list<string>
     */
    public static function foodMomotaniOver30kApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_FOOD_MOMOTANI_OVER_30K_APPROVER_EMAILS',
            [self::FOOD_MOMOTANI_OVER_30K_APPROVER_EMAIL],
        );
    }

    /**
     * 食品・緊急対応（事後申請）・3万円未満の承認者（杉浦さん）。
     * EQUIPMENT_PURCHASE_FOOD_EMERGENCY_UNDER_30K_APPROVER_EMAILS で上書き可。
     *
     * @return list<string>
     */
    public static function foodEmergencyUnder30kApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_FOOD_EMERGENCY_UNDER_30K_APPROVER_EMAILS',
            [self::FOOD_SUGIURA_APPROVER_EMAIL],
        );
    }

    /**
     * 食品・緊急対応（事後申請）・3万円以上の承認者（ティンさん）。
     * EQUIPMENT_PURCHASE_FOOD_EMERGENCY_OVER_30K_APPROVER_EMAILS で上書き可。
     *
     * @return list<string>
     */
    public static function foodEmergencyOver30kApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_FOOD_EMERGENCY_OVER_30K_APPROVER_EMAILS',
            [self::FOOD_EMERGENCY_OVER_30K_APPROVER_EMAIL],
        );
    }

    /**
     * 食品・物流センター・3万円以上の承認者（杉浦さん）。
     * EQUIPMENT_PURCHASE_FOOD_LOGISTICS_OVER_30K_APPROVER_EMAILS で上書き可。
     *
     * @return list<string>
     */
    public static function foodLogisticsOver30kApproverEmails(): array
    {
        return self::approverEmailsFromEnv(
            'EQUIPMENT_PURCHASE_FOOD_LOGISTICS_OVER_30K_APPROVER_EMAILS',
            [self::FOOD_SUGIURA_APPROVER_EMAIL],
        );
    }

    /**
     * 食品備品の指定承認者メール（全パターン合算）。
     *
     * @return list<string>
     */
    public static function allFoodDesignatedApproverEmails(): array
    {
        return array_values(array_unique(array_merge(
            self::foodMomotaniOver30kApproverEmails(),
            self::foodEmergencyUnder30kApproverEmails(),
            self::foodEmergencyOver30kApproverEmails(),
            self::foodLogisticsOver30kApproverEmails(),
        )));
    }

    /**
     * @param  list<string>  $defaults
     * @return list<string>
     */
    private static function approverEmailsFromEnv(string $envKey, array $defaults): array
    {
        $configured = env($envKey);
        if (is_string($configured) && trim($configured) !== '') {
            return array_values(array_unique(array_filter(array_map(
                static fn (string $email) => strtolower(trim($email)),
                explode(',', $configured),
            ))));
        }

        return array_values(array_unique(array_map(
            static fn (string $email) => strtolower(trim($email)),
            $defaults,
        )));
    }
}
