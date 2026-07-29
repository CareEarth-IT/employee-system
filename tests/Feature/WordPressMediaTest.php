<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_wordpress_media(): void
    {
        $this->get(route('wordpress-media.show', ['object' => 'photo.png']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_can_read_wordpress_image(): void
    {
        Storage::fake('wordpress_gcs');
        Storage::disk('wordpress_gcs')->putFileAs(
            '',
            UploadedFile::fake()->image('photo.png'),
            'photo.png',
        );

        $response = $this->actingAs(User::factory()->create())
            ->get(route('wordpress-media.show', ['object' => 'photo.png']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=3600, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_traversal_path_is_rejected(): void
    {
        Storage::fake('wordpress_gcs');

        $this->actingAs(User::factory()->create())
            ->get(route('wordpress-media.show', ['object' => '../secret.txt']))
            ->assertNotFound();
    }

    public function test_non_image_object_is_not_served(): void
    {
        Storage::fake('wordpress_gcs');
        Storage::disk('wordpress_gcs')->put('secret.txt', 'secret');

        $this->actingAs(User::factory()->create())
            ->get(route('wordpress-media.show', ['object' => 'secret.txt']))
            ->assertNotFound();
    }
}
