<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function assertReplacesLocationTo(TestResponse $response, string $url): TestResponse
    {
        $response
            ->assertOk()
            ->assertSee('location.replace', false);

        $content = $response->getContent() ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        $this->assertTrue(
            str_contains($content, $url) || str_contains($content, $path),
            "Failed asserting that location.replace targets [{$url}]."
        );

        return $response;
    }
}
