<?php

namespace Database\Seeders;

use App\Models\AffiliationHistory;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $this->seedTestUser(
            [
                'employee_id' => 'HR001',
                'last_name' => '人事',
                'first_name' => '太郎',
                'name' => '人事 太郎',
                'email' => 'hr@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_HR,
            ],
            [
                'english_name' => 'Taro Jinji',
                'name_kana' => 'ジンジ タロウ',
                'joined_at' => '2020-04-01',
                'nationality' => '日本',
            ],
            [
                'start_date' => '2020-04-01',
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '人事部',
                'section' => '人事課',
                'position' => '部長',
                'job_description' => '人事管理',
            ],
        );

        $this->seedTestUser(
            [
                'employee_id' => 'EMP001',
                'last_name' => '山田',
                'first_name' => '花子',
                'name' => '山田 花子',
                'email' => 'employee@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_EMPLOYEE,
            ],
            [
                'english_name' => 'Hanako Yamada',
                'name_kana' => 'ヤマダ ハナコ',
                'abbreviated_name' => 'ハナ',
                'joined_at' => '2023-04-01',
                'nationality' => '日本',
                'languages' => "日本語\n英語",
                'self_introduction' => 'よろしくお願いします。',
            ],
            [
                'start_date' => '2023-04-01',
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '通信部',
                'section' => '事業IT推進課',
                'position' => '一般',
                'job_description' => 'WEB制作',
            ],
        );

        $this->seedTestUser(
            [
                'employee_id' => 'GA001',
                'last_name' => '総務',
                'first_name' => '一郎',
                'name' => '総務 一郎',
                'email' => 'ga@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_EMPLOYEE,
            ],
            [
                'english_name' => 'Ichiro Somu',
                'name_kana' => 'ソウム イチロウ',
                'joined_at' => '2019-04-01',
                'nationality' => '日本',
            ],
            [
                'start_date' => '2019-04-01',
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '経理部',
                'section' => '総務課',
                'position' => '一般',
                'job_description' => '総務・備品購入管理',
            ],
        );

        $this->seedTestUser(
            [
                'employee_id' => 'REP001',
                'last_name' => '情報',
                'first_name' => '代表',
                'name' => '情報 代表',
                'email' => 'rep@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_EMPLOYEE,
            ],
            [
                'english_name' => 'Daihyo Joho',
                'name_kana' => 'ジョウホウ ダイヒョウ',
                'joined_at' => '2018-04-01',
                'nationality' => '日本',
            ],
            [
                'start_date' => '2018-04-01',
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '代表',
                'section' => null,
                'position' => '一般',
                'job_description' => '情報システム部 代表',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $affiliationData
     */
    private function seedTestUser(array $userData, array $profileData, array $affiliationData): User
    {
        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            $userData,
        );

        EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData,
        );

        AffiliationHistory::updateOrCreate(
            [
                'user_id' => $user->id,
                'start_date' => $affiliationData['start_date'],
            ],
            $affiliationData,
        );

        return $user;
    }
}
