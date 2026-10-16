<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicEnquiryRequest;
use App\Support\EnquiryForms;
use App\Support\ServiceEnquirySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class PublicEnquiryController extends Controller
{
    public function store(PublicEnquiryRequest $request, ServiceEnquirySubmission $submission): JsonResponse|RedirectResponse
    {
        $type = (string) $request->route('enquiry_type');
        $result = $submission->submit($request->safe()->except('website'), $type);

        return $request->expectsJson() ? response()->json($result, 201)
            : redirect()->to(route(EnquiryForms::pages()[$type]['route']).($type === 'study_abroad' ? '#study-enquiry' : ''))
                ->with('public_enquiry_received', [...$result, 'type' => $type]);
    }
}
