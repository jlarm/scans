<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Scan/Create');
    }
}
