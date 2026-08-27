<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * department_portals と DashboardTab の整合性検証。
 */
class DepartmentPortalConfigValidator
{
    /**
     * @return list<string> エラーメッセージ（空なら OK）
     */
    public function errors(): array
    {
        $errors = [];

        $tabKeys = collect(DashboardTab::all())->pluck('key')->all();
        $portals = DepartmentPortal::all();
        $proxyPaths = [];

        foreach ($portals as $tabKey => $portal) {
            if (! in_array($tabKey, $tabKeys, true)) {
                $errors[] = "department_portals のキー \"{$tabKey}\" が DashboardTab::TABS に存在しません。";
            }

            if (! is_array($portal)) {
                $errors[] = "department_portals.{$tabKey} は配列である必要があります。";

                continue;
            }

            $proxyPath = trim((string) ($portal['proxy_path'] ?? ''));
            if ($proxyPath === '') {
                $errors[] = "department_portals.{$tabKey}.proxy_path が空です。";

                continue;
            }

            if (isset($proxyPaths[$proxyPath])) {
                $errors[] = "proxy_path \"{$proxyPath}\" が {$proxyPaths[$proxyPath]} と {$tabKey} で重複しています。";
            } else {
                $proxyPaths[$proxyPath] = $tabKey;
            }
        }

        $portalKeys = array_keys($portals);
        foreach ($tabKeys as $tabKey) {
            if ($tabKey === 'common' || $tabKey === 'company-car') {
                continue;
            }

            if (! in_array($tabKey, $portalKeys, true)) {
                $errors[] = "DashboardTab のキー \"{$tabKey}\" に対応する department_portals エントリがありません。";
            }
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->errors() === [];
    }
}
