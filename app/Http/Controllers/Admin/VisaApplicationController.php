<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class VisaApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $applications = VisaApplication::with('visa')->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))->latest()->paginate(25)->withQueryString();

        return view('admin.visa-applications.index', compact('applications'));
    }

    public function show(VisaApplication $visaApplication): View
    {
        return view('admin.visa-applications.show', ['application' => $visaApplication->load('visa', 'user')]);
    }

    public function update(Request $request, VisaApplication $visaApplication, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['awaiting_payment', 'submitted', 'documents_requested', 'processing', 'approved', 'rejected', 'cancelled'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $before = $visaApplication->toArray();
        $visaApplication->update(['status' => $data['status']]);
        $audit->record('visa_application.updated', "Updated visa application {$visaApplication->reference} to {$data['status']}.", $visaApplication, $before, $visaApplication->fresh()->toArray());

        return back()->with('success', 'Application status updated.');
    }
}
