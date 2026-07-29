<?php

namespace Tests\Feature;

use App\Services\SiteBrandingStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_branding_assets_are_served(): void
    {
        Storage::fake('public');
        config(['filesystems.branding_disk' => 'public']);

        SiteBrandingStorage::syncToTargetDisk(overwrite: true);

        $this->get(route('branding.asset', ['path' => 'group-web.png']))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->get(route('branding.asset', ['path' => 'care-earth-group-webpage.png']))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_unknown_branding_asset_returns_not_found(): void
    {
        $this->get(route('branding.asset', ['path' => 'secret.png']))
            ->assertNotFound();
    }
}
