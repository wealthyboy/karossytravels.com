<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PartnerEnquiry;
use App\Support\TravelLogger;
use App\Support\EnquiryMailDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CarPartnerController extends Controller
{
    public function index(): View
    {
        return view('cars.partners');
    }

    public function store(Request $request, TravelLogger $logger, EnquiryMailDelivery $mail): JsonResponse|RedirectResponse
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
        $enquiry = DB::transaction(function () use ($data, $request, $logger): PartnerEnquiry {
            $enquiry = PartnerEnquiry::create([...$data, 'type' => 'driver', 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500)]);
            $logger->record('car', 'partner_enquiry', null, [], ['reference' => $enquiry->id]);
            return $enquiry;
        });
        $mail->deliver($enquiry);

        $message = 'Thank you. Our partnership team will contact you shortly.';
        return $request->expectsJson() ? response()->json(['message' => $message, 'reference' => $enquiry->id], 201)
            : redirect()->route('cars.partners')->with('partner_enquiry_received', $message.' Reference: '.$enquiry->id);
    }
}
