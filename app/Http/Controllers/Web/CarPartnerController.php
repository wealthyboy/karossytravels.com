<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PartnerEnquiry;
use App\Support\TravelLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CarPartnerController extends Controller
{
    public function index(): View
    {
        return view('cars.partners');
    }

    public function store(Request $request, TravelLogger $logger): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:120'],
            'vehicle_year' => ['nullable', 'digits:4'],
            'message' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ]);
        unset($data['terms']);
        $enquiry = PartnerEnquiry::create([...$data, 'type' => 'driver', 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500)]);
        $logger->record('car', 'partner_enquiry', null, ['city' => $enquiry->city, 'vehicle_type' => $enquiry->vehicle_type], ['reference' => $enquiry->id]);

        return response()->json(['message' => 'Thank you. Our partnership team will contact you shortly.', 'reference' => $enquiry->id], 201);
    }
}
