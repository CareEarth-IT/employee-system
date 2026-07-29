<?php

namespace Tests\Unit;

use League\Flysystem\GoogleCloudStorage\PortableVisibilityHandler;
use Tests\TestCase;

class FilesystemGcsConfigTest extends TestCase
{
    public function test_gcs_disk_uses_uniform_bucket_visibility(): void
    {
        $this->assertSame(
            PortableVisibilityHandler::NO_PREDEFINED_VISIBILITY,
            config('filesystems.disks.gcs.visibility'),
        );
    }
}
