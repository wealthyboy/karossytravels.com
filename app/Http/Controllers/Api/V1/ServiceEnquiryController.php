<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarHireEnquiryRequest;
use App\Http\Requests\VisaEnquiryRequest;
use App\Http\Responses\ApiResponse;
use App\Support\CountryOptions;
use App\Support\EnquiryOptions;
use App\Support\ServiceEnquirySubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceEnquiryController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'car_hire' => [
                'hire_types' => EnquiryOptions::HIRE_TYPES, 'return_required' => EnquiryOptions::RETURN_REQUIRED,
                'vehicle_preferences' => EnquiryOptions::VEHICLES,
            ],
            'visa_enquiry' => [
                'countries' => array_values(CountryOptions::all()),
                'visa_types' => collect(array_keys(EnquiryOptions::VISA_TYPES))->mapWithKeys(fn (string $purpose): array => [$purpose => EnquiryOptions::visaTypes($purpose)]),
                'document_questions' => EnquiryOptions::DOCUMENT_QUESTIONS,
                'occupations' => EnquiryOptions::OCCUPATIONS, 'timelines' => EnquiryOptions::TIMELINES,
                'duration_units' => ['Days', 'Weeks', 'Months'],
            ],
        ]);
    }

    public function carHire(CarHireEnquiryRequest $request, ServiceEnquirySubmission $submission): JsonResponse
    {
        return ApiResponse::success($request, $submission->submit($request->validated(), 'car_hire'), status: 201);
    }

    public function visa(VisaEnquiryRequest $request, ServiceEnquirySubmission $submission): JsonResponse
    {
        return ApiResponse::success($request, $submission->submit($request->validated(), 'visa_enquiry'), status: 201);
    }
}
