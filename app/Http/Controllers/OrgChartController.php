<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrgChartController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED = [
        'index.html',
        'note.html',
        'resources/sheet.css',
    ];

    /**
     * Serve the static organization-chart HTML package (login required).
     */
    public function show(?string $path = null): BinaryFileResponse
    {
        $relative = $this->normalizePath($path);
        $fullPath = resource_path('static/org-chart'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if (! is_file($fullPath)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION))) {
            'html', 'htm' => 'text/html; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            default => 'application/octet-stream',
        };

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function normalizePath(?string $path): string
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return 'index.html';
        }

        if (! in_array($path, self::ALLOWED, true)) {
            abort(404);
        }

        return $path;
    }
}
