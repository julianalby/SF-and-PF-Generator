<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSfRequest;
use App\Models\SfRecord;
use App\Services\FormRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SfController extends Controller
{
    public function create(): View
    {
        // A fresh idempotency token per rendering of the form.
        return view('sf.create', ['token' => (string) Str::uuid()]);
    }

    public function store(StoreSfRequest $request, FormRecordService $forms): RedirectResponse
    {
        $record = $forms->createSf(
            $request->user(),
            $request->safe()->only(['vnid', 'customer_name', 'service']),
            $request->validated('submission_token'),
        );

        // Post/Redirect/Get: refreshing the result page can never re-submit the form.
        return redirect()->route('sf.show', $record)->with('created', true);
    }

    /**
     * Result page for one SF: visible to the person who created it and to admins.
     */
    public function show(Request $request, SfRecord $sfRecord): View
    {
        abort_unless($request->user()->isAdmin() || $sfRecord->isOwnedBy($request->user()), 403);

        return view('sf.show', ['record' => $sfRecord]);
    }
}
