<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarHireEnquiryRequest;
use App\Http\Requests\VisaEnquiryRequest;
use App\Support\ServiceEnquirySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ServiceEnquiryController extends Controller
{
    public function carHire(): View
    {
        return view('enquiries.car-hire');
    }

    public function visa(): View
    {
        return view('enquiries.visa');
    }

    public function storeCarHire(CarHireEnquiryRequest $request, ServiceEnquirySubmission $submission): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $submission->submit($request->validated(), 'car_hire'), 'car_hire');
    }

    public function storeVisa(VisaEnquiryRequest $request, ServiceEnquirySubmission $submission): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $submission->submit($request->validated(), 'visa_enquiry'), 'visa_enquiry');
    }

    private function respond(Request $request, array $result, string $type): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json($result, 201)
            : redirect()->route($type === 'car_hire' ? 'cars.hire' : 'visas.enquiry')->with('enquiry_received', $result);
    }
}
