<?php

namespace App\Http\Controllers;

use App\Services\DriveStaffSyncService;
use Illuminate\Http\RedirectResponse;

class DriveAppSyncController extends Controller
{
    public function __construct(
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $result = $this->driveStaffSync->syncUserWithDetails(auth()->user());

        if (! $result->ok) {
            return back()->withErrors(['drive_sync' => $result->error ?? '社用車アプリへの送信に失敗しました。']);
        }

        $message = $result->created
            ? '社用車アプリへ社員情報を送信しました（新規登録）。'
            : '社用車アプリへ社員情報を送信しました。';

        return back()->with('success', $message);
    }
}
