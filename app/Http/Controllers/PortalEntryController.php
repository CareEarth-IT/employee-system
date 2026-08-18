<?php

namespace App\Http\Controllers;

use App\Services\PortalEntryGate;
use App\Services\SateraitoSsoService;
use Illuminate\View\View;

class PortalEntryController extends Controller
{
    public function __invoke(SateraitoSsoService $sso, PortalEntryGate $gate): View
    {
        return view('auth.portal-entry-required', [
            'ssoEntryUrl' => $sso->entryUrl(),
            'showPasswordLogin' => ! $gate->isRequired(),
        ]);
    }

    public function loggedOut(): View
    {
        return view('auth.logged-out');
    }
}
