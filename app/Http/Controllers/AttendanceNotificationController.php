<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceNotificationStoreRequest;
use App\Models\AttendanceNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceNotificationController extends Controller
{
    public function create(): View
    {
        $this->authorizeAttendanceAccess();

        return view('attendance.create', [
            'user' => auth()->user(),
        ]);
    }

    public function store(AttendanceNotificationStoreRequest $request): RedirectResponse
    {
        $this->authorizeAttendanceAccess();

        $notification = auth()->user()->attendanceNotifications()->create($request->validated());

        return redirect()->route('attendance-notifications.complete', $notification);
    }

    public function complete(AttendanceNotification $attendanceNotification): View
    {
        $this->authorizeAttendanceAccess();

        if ($attendanceNotification->user_id !== auth()->id()) {
            abort(403);
        }

        return view('attendance.complete', [
            'notification' => $attendanceNotification,
        ]);
    }

    private function authorizeAttendanceAccess(): void
    {
        if (! auth()->user()->canViewAttendanceSection()) {
            abort(403);
        }
    }
}
