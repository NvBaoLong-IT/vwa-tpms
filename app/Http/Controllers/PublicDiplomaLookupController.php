<?php

namespace App\Http\Controllers;

use App\Models\DiplomaLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicDiplomaLookupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        if (! $request->has('code') && ! $request->expectsJson()) {
            return response()->view('diploma.lookup')->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $data = $request->validate(['code' => ['required', 'string', 'min:3', 'max:100']]);
        $ledger = DiplomaLedger::with('student:id,full_name')
            ->where(fn ($query) => $query->where('reg_number', $data['code'])->orWhere('serial_number', $data['code']))
            ->whereIn('status', ['issued', 'cancelled'])->first();
        $result = $ledger === null ? ['valid' => false] : [
            'holder_name' => $ledger->holder_name ?: $ledger->student?->full_name,
            'issue_date' => $ledger->issue_date?->format('Y-m-d'),
            'diploma_type' => $ledger->diploma_type,
            'valid' => $ledger->status === 'issued',
        ];

        if ($request->expectsJson()) {
            return response()->json($result)->header('Cache-Control', 'no-store, private')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->view('diploma.lookup', ['result' => $result])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
