<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DriveStaffSyncService
{
    public function isConfigured(): bool
    {
        $baseUrl = trim((string) config('services.drive_app.url', ''));
        $secret = trim((string) config('services.drive_app.sync_secret', ''));

        return $baseUrl !== '' && $secret !== '';
    }

    public function syncUser(User $user): bool
    {
        return $this->syncUserWithDetails($user)->ok;
    }

    public function syncUserWithDetails(User $user): DriveStaffSyncResult
    {
        if (! $this->isConfigured()) {
            return DriveStaffSyncResult::notConfigured();
        }

        $user->loadMissing(['profile', 'hrDetail', 'affiliationHistories']);

        $payload = $this->buildPayload($user);
        if ($payload === null) {
            return DriveStaffSyncResult::invalidProfile();
        }

        return $this->postStaff($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildPayload(User $user): ?array
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return null;
        }

        $affiliation = $user->currentAffiliation();
        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return null;
        }

        $department = trim((string) $affiliation->department);
        if ($department === '') {
            return null;
        }

        return [
            'email' => $email,
            'name' => $user->displayName(),
            'departmentName' => $department,
            'employmentType' => $this->resolveEmploymentType($user, $email),
        ];
    }

    private function resolveEmploymentType(User $user, string $email): string
    {
        $mapped = $this->mapEmploymentType($user->displayEmploymentType());
        if ($mapped !== null) {
            return $mapped;
        }

        return str_ends_with(strtolower($email), '@careearth.info') ? 'employee' : 'part_time';
    }

    private function mapEmploymentType(string $label): ?string
    {
        return match ($label) {
            '正社員' => 'employee',
            'アルバイト' => 'part_time',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postStaff(array $payload): DriveStaffSyncResult
    {
        $baseUrl = rtrim(trim((string) config('services.drive_app.url', '')), '/');
        $secret = trim((string) config('services.drive_app.sync_secret', ''));

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Employee-Site-Secret' => $secret,
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/api/integrations/employee-site/staff-profiles", $payload);

            if ($response->successful() || $response->status() === 207) {
                $body = $response->json();
                if (is_array($body) && ($body['ok'] ?? false) === true) {
                    return DriveStaffSyncResult::success($this->wasCreated($body, (string) $payload['email']));
                }

                Log::warning('Drive staff sync returned errors', [
                    'email' => $payload['email'],
                    'response' => $body,
                ]);

                return DriveStaffSyncResult::failure($this->extractErrorMessage($body, $payload));
            }

            Log::warning('Drive staff sync HTTP error', [
                'email' => $payload['email'],
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return DriveStaffSyncResult::failure($this->httpErrorMessage($response->status()));
        } catch (\Throwable $e) {
            Log::warning('Drive staff sync failed', [
                'email' => $payload['email'],
                'error' => $e->getMessage(),
            ]);

            return DriveStaffSyncResult::failure('社用車アプリへの接続に失敗しました。しばらくしてから再度お試しください。');
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function wasCreated(?array $body, string $email): bool
    {
        $results = $body['results'] ?? null;
        if (! is_array($results)) {
            return false;
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            if (($result['email'] ?? '') === $email && ($result['created'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $payload
     */
    private function extractErrorMessage(?array $body, array $payload): string
    {
        $results = $body['results'] ?? null;
        if (is_array($results)) {
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                if (($result['email'] ?? '') === $payload['email'] && ! empty($result['error'])) {
                    return $this->translateDriveError((string) $result['error'], $payload);
                }
            }
        }

        $error = is_array($body) ? trim((string) ($body['error'] ?? '')) : '';
        if ($error !== '') {
            return $this->translateDriveError($error, $payload);
        }

        return '社用車アプリへの送信に失敗しました。管理者にお問い合わせください。';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function translateDriveError(string $error, array $payload): string
    {
        if (str_contains($error, 'must match drive departments')) {
            $department = (string) ($payload['departmentName'] ?? '');

            return "社用車アプリに「{$department}」が登録されていません。管理者に部署名の登録を依頼してください。";
        }

        return '社用車アプリへの送信に失敗しました。管理者にお問い合わせください。';
    }

    private function httpErrorMessage(int $status): string
    {
        return match ($status) {
            403 => '社用車アプリとの認証に失敗しました。管理者にお問い合わせください。',
            503 => '社用車アプリの連携設定が完了していません。管理者にお問い合わせください。',
            default => '社用車アプリへの接続に失敗しました。しばらくしてから再度お試しください。',
        };
    }
}
