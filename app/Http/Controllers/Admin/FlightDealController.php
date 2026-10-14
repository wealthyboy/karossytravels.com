<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\FlightDeal;
use App\Support\AuditLogger;
use App\Travel\Pricing\FlightPromotionInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FlightDealController extends Controller
{
    public function index(): View
    {
        return view('admin.flight-deals.index', ['deals' => FlightDeal::query()->with('airline')->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.flight-deals.create', $this->formData());
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $deal = FlightDeal::create($this->validated($request));
        $audit->record('flight-deal.created', "Created a {$deal->discount_type} deal for {$deal->airline_summary}.", $deal, after: $deal->toArray());

        return redirect()->route('admin.flight-deals.index')->with('success', 'Flight deal created.');
    }

    public function edit(FlightDeal $flightDeal): View
    {
        return view('admin.flight-deals.edit', ['deal' => $flightDeal->load('airline'), ...$this->formData()]);
    }

    public function update(Request $request, FlightDeal $flightDeal, AuditLogger $audit): RedirectResponse
    {
        $before = $flightDeal->toArray();
        $flightDeal->update($this->validated($request));
        $audit->record('flight-deal.updated', "Updated the deal for {$flightDeal->fresh()->airline_summary}.", $flightDeal, $before, $flightDeal->fresh()->toArray());

        return redirect()->route('admin.flight-deals.index')->with('success', 'Flight deal updated.');
    }

    public function destroy(FlightDeal $flightDeal, AuditLogger $audit): RedirectResponse
    {
        $audit->record('flight-deal.deleted', "Deleted the deal for {$flightDeal->airline_summary}.", $flightDeal, $flightDeal->toArray());
        DB::transaction(function () use ($flightDeal): void {
            $flightDeal->homepageOffers()->update(['promotion_enabled' => false]);
            $flightDeal->delete();
        });

        return back()->with('success', 'Flight deal deleted.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $airlines = Airline::query()->where('active', true)->orderBy('name')->get();

        return ['airlines' => $airlines];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'origin_airport' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination_airport' => ['nullable', 'string', 'size:3', 'different:origin_airport', 'regex:/^[A-Za-z]{3}$/'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data = [...$data, ...app(FlightPromotionInput::class)->validate($request)];
        $data['origin_airport'] = strtoupper((string) ($data['origin_airport'] ?? '')) ?: null;
        $data['destination_airport'] = strtoupper((string) ($data['destination_airport'] ?? '')) ?: null;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
