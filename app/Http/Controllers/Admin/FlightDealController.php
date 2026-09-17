<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\FlightDeal;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $audit->record('flight-deal.created', "Created a {$deal->discount_type} deal for {$deal->airline->name}.", $deal, after: $deal->toArray());

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
        $audit->record('flight-deal.updated', "Updated the deal for {$flightDeal->fresh('airline')->airline->name}.", $flightDeal, $before, $flightDeal->fresh()->toArray());

        return redirect()->route('admin.flight-deals.index')->with('success', 'Flight deal updated.');
    }

    public function destroy(FlightDeal $flightDeal, AuditLogger $audit): RedirectResponse
    {
        $audit->record('flight-deal.deleted', "Deleted the deal for {$flightDeal->airline->name}.", $flightDeal, $flightDeal->toArray());
        $flightDeal->delete();

        return back()->with('success', 'Flight deal deleted.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $airlines = Airline::query()->where('active', true)->orderBy('country')->orderBy('name')->get();

        return ['airlines' => $airlines, 'countries' => $airlines->pluck('country', 'country_code')->unique()->sort()];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'airline_id' => ['required', 'integer', Rule::exists('airlines', 'id')->where(fn ($query) => $query->where('active', true))],
            'origin_airport' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'destination_airport' => ['nullable', 'string', 'size:3', 'different:origin_airport', 'regex:/^[A-Za-z]{3}$/'],
            'travel_from' => ['required', 'date'],
            'travel_until' => ['required', 'date', 'after_or_equal:travel_from'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'discount_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($data['discount_type'] === 'percentage' && (float) $data['discount_value'] > 100) {
            abort(422, 'Percentage discounts cannot be greater than 100.');
        }

        $data['origin_airport'] = strtoupper((string) ($data['origin_airport'] ?? '')) ?: null;
        $data['destination_airport'] = strtoupper((string) ($data['destination_airport'] ?? '')) ?: null;
        $data['discount_currency'] = strtoupper((string) ($data['discount_currency'] ?? '')) ?: null;
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['active'] = $request->boolean('active');

        if ($data['discount_type'] === 'percentage') $data['discount_currency'] = null;

        return $data;
    }
}
