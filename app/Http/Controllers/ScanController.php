<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class ScanController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Scan/Create');
    }
}
