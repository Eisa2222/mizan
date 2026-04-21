<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\SuperAdmin\Queries\DashboardStatsQuery;

class DashboardController extends Controller
{
    public function __invoke(DashboardStatsQuery $stats): View
    {
        return view('super-admin.dashboard', $stats->run());
    }
}
