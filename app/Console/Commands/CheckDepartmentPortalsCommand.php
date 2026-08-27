<?php

namespace App\Console\Commands;

use App\Support\DepartmentPortalConfigValidator;
use Illuminate\Console\Command;

class CheckDepartmentPortalsCommand extends Command
{
    protected $signature = 'department-portals:check';

    protected $description = 'DashboardTab と department_portals 設定の整合性を検証する';

    public function handle(DepartmentPortalConfigValidator $validator): int
    {
        $errors = $validator->errors();

        if ($errors === []) {
            $this->info('department_portals 設定は DashboardTab と整合しています。');

            return self::SUCCESS;
        }

        $this->error('department_portals 設定に問題があります:');
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }

        return self::FAILURE;
    }
}
