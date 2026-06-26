<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscoveryIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('discovery.index', [
            'organizations' => $request->user()
                ->organizations()
                ->with(['connections.latestDiscoverySnapshot'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
