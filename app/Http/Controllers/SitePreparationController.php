<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SitePreparationController extends Controller
{
    public function __invoke(): View
    {
        return view('site-preparation');
    }
}
