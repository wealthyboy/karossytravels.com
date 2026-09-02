<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerEnquiry;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PartnerEnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = PartnerEnquiry::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$request->string('q').'%')->orWhere('email', 'like', '%'.$request->string('q').'%')->orWhere('city', 'like', '%'.$request->string('q').'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.partner-enquiries.index', compact('enquiries'));
    }

    public function show(PartnerEnquiry $partnerEnquiry): View
    {
        return view('admin.partner-enquiries.show', ['enquiry' => $partnerEnquiry]);
    }

    public function update(Request $request, PartnerEnquiry $partnerEnquiry, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'reviewing', 'approved', 'declined'])]]);
        $before = $partnerEnquiry->toArray();
        $partnerEnquiry->update($data);
        $audit->record('partner_enquiry.updated', "Updated partner enquiry {$partnerEnquiry->id}.", $partnerEnquiry, $before, $partnerEnquiry->fresh()->toArray());

        return back()->with('success', 'Partner enquiry updated.');
    }
}
