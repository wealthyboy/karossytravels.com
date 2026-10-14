<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarHireEnquiryRequest;
use App\Http\Requests\VisaEnquiryRequest;
use App\Models\ServiceEnquiry;
use App\Support\TravelLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function storeCarHire(CarHireEnquiryRequest $request, TravelLogger $logger): JsonResponse|RedirectResponse
    {
        return $this->store($request, $request->validated(), 'car_hire', 'phone', $logger);
    }

    public function storeVisa(VisaEnquiryRequest $request, TravelLogger $logger): JsonResponse|RedirectResponse
    {
        return $this->store($request, $request->validated(), 'visa_enquiry', 'phone_whatsapp', $logger);
    }

    private function store(Request $request, array $data, string $type, string $phoneKey, TravelLogger $logger): JsonResponse|RedirectResponse
    {
        $enquiry = DB::transaction(function () use ($data, $type, $phoneKey, $logger): ServiceEnquiry {
            $enquiry = ServiceEnquiry::create([
                'reference' => ($type === 'car_hire' ? 'KCH-' : 'KVE-').now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'type' => $type, 'status' => 'new', 'name' => $data['full_name'],
                'email' => $data['email'], 'phone' => $data[$phoneKey],
                'details' => collect($data)->except(['full_name', 'email', $phoneKey])->all(),
            ]);
            // Background and contact answers never enter operational logs.
            $logger->record($type === 'car_hire' ? 'car' : 'visa', 'enquiry', null, [], ['reference' => $enquiry->reference]);

            return $enquiry;
        });
        $message = $type === 'car_hire'
            ? 'Thank you for your request. Our Car Hire team will review your requirements and contact you with suitable vehicle options and pricing.'
            : 'Thank you for your enquiry. Our Visa Team will review the information provided and contact you with the applicable requirements, fees and next steps.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'reference' => $enquiry->reference], 201)
            : redirect()->route($type === 'car_hire' ? 'cars.hire' : 'visas.enquiry')->with('enquiry_received', ['message' => $message, 'reference' => $enquiry->reference]);
    }
}
