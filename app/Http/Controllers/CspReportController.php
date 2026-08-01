<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives browser-sent Content-Security-Policy violation reports. No auth —
 * browsers POST here directly per the policy's report-uri. Logged to a
 * dedicated channel so violations can be reviewed before flipping
 * config('security.csp_enforce') to true.
 */
class CspReportController extends Controller
{
    /** Log the reported violation and acknowledge receipt. */
    public function __invoke(Request $request): Response
    {
        Log::channel('csp')->warning('CSP violation', $request->json()->all());

        return response()->noContent();
    }
}
