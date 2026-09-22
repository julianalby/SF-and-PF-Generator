<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePfRequest;
use App\Models\PfRecord;
use App\Services\FormRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PfController extends Controller
{
    public function create(): View
    {
        // A fresh idempotency token per rendering of the form.
        return view('pf.create', ['token' => (string) Str::uuid()]);
    }

    public function store(StorePfRequest $request, FormRecordService $forms): RedirectResponse
    {
        $record = $forms->createPf(
            $request->user(),
            $request->safe()->only(['project_name', 'sf_number', 'vnid', 'customer_name']),
            $request->validated('submission_token'),
        );

        return redirect()->route('pf.show', $record)->with('created', true);
    }

    /**
     * Result page for one PF: visible to the person who created it and to admins.
     */
    public function show(Request $request, PfRecord $pfRecord): View
    {
        abort_unless($request->user()->isAdmin() || $pfRecord->isOwnedBy($request->user()), 403);

        return view('pf.show', ['record' => $pfRecord]);
    }
}
