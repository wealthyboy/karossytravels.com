<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceEnquiry;
use App\Support\AuditLogger;
use App\Support\EnquiryForms;
use App\Support\EnquiryMailDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ServiceEnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['type' => ['nullable', Rule::in(array_keys(EnquiryForms::LABELS))], 'status' => ['nullable', Rule::in(['new', 'contacted', 'reviewing', 'completed', 'closed'])], 'q' => ['nullable', 'string', 'max:190']]);
        $enquiries = ServiceEnquiry::query()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.service-enquiries.index', compact('enquiries'));
    }

    public function show(ServiceEnquiry $serviceEnquiry): View
    {
        return view('admin.service-enquiries.show', ['enquiry' => $serviceEnquiry]);
    }

    public function update(Request $request, ServiceEnquiry $serviceEnquiry, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'reviewing', 'completed', 'closed'])]]);
        $before = ['status' => $serviceEnquiry->status];
        $serviceEnquiry->update($data);
        $audit->record('service_enquiry.updated', 'Updated enquiry '.$serviceEnquiry->reference, $serviceEnquiry, $before, $data);

        return back()->with('success', 'Enquiry status updated.');
    }

    public function retryEmail(ServiceEnquiry $serviceEnquiry, EnquiryMailDelivery $delivery, AuditLogger $audit): RedirectResponse
    {
        abort_unless(collect($serviceEnquiry->email_delivery ?? [])->contains(fn ($entry) => data_get($entry, 'state') === 'failed'), 422);
        $delivery->deliver($serviceEnquiry);
        $audit->record('service_enquiry.email_retried', 'Retried failed enquiry email delivery.', $serviceEnquiry);
        return back()->with('success', 'Failed email delivery retried. Check the delivery statuses below.');
    }
}
