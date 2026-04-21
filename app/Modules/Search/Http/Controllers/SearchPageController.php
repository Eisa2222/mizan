<?php

namespace Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SearchPageController extends Controller
{
    public function __invoke(): View
    {
        return view('search.index');
    }
}
