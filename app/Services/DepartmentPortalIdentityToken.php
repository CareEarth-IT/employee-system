<?php

namespace App\Services;

use App\Support\DepartmentPortal;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DepartmentPortalIdentityToken
{
    public function token(string $tabKey, string $audience): ?string
    {
        $audience = rtrim($audience, '/');

        if (! DepartmentPortal::shouldUseIdentityToken($tabKey)) {
            return null;
        }

        $response = Http::timeout(5)
            ->withHeaders(['Metadata-Flavor' => 'Google'])
            ->get(
                'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/identity',
                [
                    'audience' => $audience,
                    'format' => 'full',
                ],
            );

        if (! $response->successful()) {
            throw new RuntimeException('社内サイト用の認証トークンを取得できませんでした。');
        }

        $token = trim($response->body());

        return $token !== '' ? $token : null;
    }
}
