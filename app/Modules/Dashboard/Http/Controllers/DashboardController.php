<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Dashboard\Queries\DashboardStatsQuery;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStatsQuery $stats): View
    {
        return view('dashboard', $stats->run($request->user()));
    }
}
