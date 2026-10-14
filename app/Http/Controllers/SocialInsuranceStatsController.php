<?php

namespace App\Http\Controllers;

use App\Support\SocialInsuranceCompanyStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialInsuranceStatsController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($viewer?->canViewSocialInsuranceStats(), 403);

        $rows = SocialInsuranceCompanyStats::countsByCompany();

        return view('employees.social-insurance-stats', [
            'rows' => $rows,
            'threshold' => SocialInsuranceCompanyStats::THRESHOLD,
            'totalCount' => array_sum(array_column($rows, 'count')),
        ]);
    }
}
