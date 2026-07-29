<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateEquipmentApproverCommand extends Command
{
    protected $signature = 'employee:create-approver
        {email : ログイン用メールアドレス}
        {--password= : ログインパスワード（未指定時は password）}
        {--type=ga : 承認者種別: ga=経理部・総務課, manager=部長, rep=情報システム部指定承認者, global=全部署・上長以上}
        {--name= : 表示名（未指定時はメールの@前）}
        {--employee-id= : 社員番号（未指定時は自動採番）}
        {--department=通信部 : 部長の場合の所属「部」}
        {--section=事業IT推進課 : 部長の場合の所属「課」}';

    protected $description = '備品購入を承認できるアカウントを1件作成する';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: 'password');
        $type = (string) $this->option('type');

        [$lastName, $firstName, $name, $affiliation] = $this->resolveIdentity($email, $type);

        $employeeId = (string) ($this->option('employee-id') ?: $this->defaultEmployeeId($type));

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'employee_id' => $employeeId,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'name' => $this->option('name') ?: $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_EMPLOYEE,
            ],
        );

        EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name_kana' => '',
                'joined_at' => now()->toDateString(),
                'nationality' => '日本',
            ],
        );

        AffiliationHistory::updateOrCreate(
            [
                'user_id' => $user->id,
                'start_date' => $affiliation['start_date'],
            ],
            $affiliation,
        );

        $this->info('承認者アカウントを作成しました。');
        $this->table(
            ['項目', '値'],
            [
                ['メール', $email],
                ['パスワード', $password],
                ['種別', $this->typeLabel($type)],
                ['氏名', $user->displayName()],
                ['所属', $affiliation['department'].' / '.$affiliation['section']],
                ['役職', $affiliation['position']],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: array<string, mixed>}
     */
    private function resolveIdentity(string $email, string $type): array
    {
        $startDate = now()->toDateString();

        return match ($type) {
            'manager' => [
                '承認',
                '部長',
                '承認 部長',
                [
                    'start_date' => $startDate,
                    'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                    'company' => 'CareEarth',
                    'location' => '大阪',
                    'department' => (string) $this->option('department'),
                    'section' => (string) $this->option('section'),
                    'position' => '部長',
                    'job_description' => '備品購入 部長承認',
                ],
            ],
            'rep' => [
                '情報',
                '承認',
                '情報 承認',
                [
                    'start_date' => $startDate,
                    'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                    'company' => 'CareEarth',
                    'location' => '大阪',
                    'department' => '情報システム部',
                    'section' => '事業IT推進課',
                    'position' => '一般',
                    'job_description' => '情報システム部 指定承認',
                ],
            ],
            'global' => [
                '上長',
                '確認',
                '上長',
                [
                    'start_date' => $startDate,
                    'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                    'company' => 'CareEarth',
                    'location' => '大阪',
                    'department' => '管理本部',
                    'section' => '備品承認',
                    'position' => '上長',
                    'job_description' => '備品購入 全部署・上長以上承認',
                ],
            ],
            default => [
                '総務',
                '承認',
                '総務 承認',
                [
                    'start_date' => $startDate,
                    'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                    'company' => 'CareEarth',
                    'location' => '大阪',
                    'department' => '経理部',
                    'section' => '総務課',
                    'position' => '一般',
                    'job_description' => '備品購入 総務承認',
                ],
            ],
        };
    }

    private function defaultEmployeeId(string $type): string
    {
        $prefix = match ($type) {
            'manager' => 'MGR',
            'rep' => 'REP',
            'global' => 'GLB',
            default => 'GA',
        };

        for ($n = 1; $n <= 999; $n++) {
            $id = sprintf('%s%03d', $prefix, $n);
            if (! User::where('employee_id', $id)->exists()) {
                return $id;
            }
        }

        return $prefix.now()->format('His');
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'manager' => '部長（3万円以上・同部署）',
            'rep' => '情報システム部指定承認者',
            'global' => '全部署・上長以上承認',
            default => '経理部・総務課（3万円未満など）',
        };
    }
}
