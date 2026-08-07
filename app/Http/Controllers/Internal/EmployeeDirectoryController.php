<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmployeeDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDirectoryController extends Controller
{
    public function __construct(
        private readonly EmployeeDirectoryService $employeeDirectory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', EmployeeDirectoryService::DEFAULT_STATUS));
        $department = $request->query('department');
        $departmentFilter = is_string($department) ? trim($department) : null;

        $employees = $this->employeeDirectory->search($keyword, $status, $departmentFilter);

        return response()->json([
            'employees' => $employees,
            'meta' => [
                'count' => count($employees),
                'status' => $status !== '' ? $status : EmployeeDirectoryService::DEFAULT_STATUS,
                'department' => $departmentFilter,
                'statuses' => User::EMPLOYMENT_STATUS_OPTIONS,
            ],
        ]);
    }
}
