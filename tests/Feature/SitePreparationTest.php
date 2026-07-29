<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitePreparationTest extends TestCase
{
    public function test_root_redirects_to_site_preparation(): void
    {
        $this->get('/')
            ->assertRedirect('/index');
    }

    public function test_index_shows_site_preparation_page(): void
    {
        $this->get('/index')
            ->assertOk()
            ->assertSee('サイト準備中', false)
            ->assertSee('CE-Group 社員専用サイトは現在準備中です。', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('ログイン画面へ', false)
            ->assertDontSee(route('login'), false);
    }

    public function test_index_is_accessible_without_login(): void
    {
        $this->get(route('site-preparation'))
            ->assertOk()
            ->assertDontSee('ログアウト', false);
    }

    public function test_robots_txt_disallows_all_crawlers(): void
    {
        $contents = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /', $contents);
        $this->assertStringNotContainsString('Sitemap', $contents);
    }
}
