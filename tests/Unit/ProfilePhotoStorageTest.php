<?php

namespace Tests\Unit;

use App\Services\ProfilePhotoStorage;
use Tests\TestCase;

class ProfilePhotoStorageTest extends TestCase
{
    public function test_candidate_disks_includes_legacy_when_target_is_gcs(): void
    {
        config(['filesystems.profile_photos_disk' => 'gcs']);

        $this->assertSame(['gcs', 'public'], ProfilePhotoStorage::candidateDisks());
    }

    public function test_candidate_disks_is_single_disk_when_target_is_public(): void
    {
        config(['filesystems.profile_photos_disk' => 'public']);

        $this->assertSame(['public'], ProfilePhotoStorage::candidateDisks());
    }
}
