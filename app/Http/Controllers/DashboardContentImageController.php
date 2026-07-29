<?php

namespace App\Http\Controllers;

use App\Services\DashboardContentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardContentImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $request->user()->canManageDashboardContents()) {
            abort(403, 'Top Page のコンテンツを編集する権限がありません。');
        }

        $department = (string) $request->input('department', '社員共通');
        if (\App\Support\DashboardTab::findByDepartment($department) === null) {
            abort(403, 'この部署のコンテンツを編集する権限がありません。');
        }

        try {
            $path = DashboardContentStorage::storeImage($request->file('image'));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : '画像のアップロードに失敗しました。',
            ], 500);
        }

        return response()->json([
            'location' => DashboardContentStorage::assetUrl($path),
        ]);
    }
}
