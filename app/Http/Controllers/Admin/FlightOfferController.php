<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlightOffer;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class FlightOfferController extends Controller
{
    public function index(): View
    {
        return view('admin.flight-offers.index', [
            'offers' => FlightOffer::query()->orderBy('sort_order')->orderBy('departure_date')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.flight-offers.create');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('flight-offers', 'public');
        }

        $offer = FlightOffer::create($data);
        $audit->record('flight-offer.created', "Created flight offer {$offer->origin_airport} to {$offer->destination_airport}.", $offer, after: $offer->toArray());

        return redirect()->route('admin.flight-offers.edit', $offer)->with('success', 'Flight offer created.');
    }

    public function edit(FlightOffer $flightOffer): View
    {
        return view('admin.flight-offers.edit', compact('flightOffer'));
    }

    public function update(Request $request, FlightOffer $flightOffer, AuditLogger $audit): RedirectResponse
    {
        $before = $flightOffer->toArray();
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data, $flightOffer);

        if ($request->hasFile('image')) {
            if ($flightOffer->image_path) {
                Storage::disk('public')->delete($flightOffer->image_path);
            }
            $data['image_path'] = $request->file('image')->store('flight-offers', 'public');
        }

        $flightOffer->update($data);
        $audit->record('flight-offer.updated', "Updated flight offer {$flightOffer->origin_airport} to {$flightOffer->destination_airport}.", $flightOffer, $before, $flightOffer->fresh()->toArray());

        return back()->with('success', 'Flight offer updated.');
    }

    public function destroy(FlightOffer $flightOffer, AuditLogger $audit): RedirectResponse
    {
        $audit->record('flight-offer.deleted', "Deleted flight offer {$flightOffer->origin_airport} to {$flightOffer->destination_airport}.", $flightOffer, $flightOffer->toArray());
        if ($flightOffer->image_path) {
            Storage::disk('public')->delete($flightOffer->image_path);
        }
        $flightOffer->delete();

        return back()->with('success', 'Flight offer deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'origin_airport' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'origin_city' => ['required', 'string', 'max:100'],
            'destination_airport' => ['required', 'string', 'size:3', 'different:origin_airport', 'regex:/^[A-Za-z]{3}$/'],
            'destination_city' => ['required', 'string', 'max:100'],
            'airline_name' => ['required', 'string', 'max:120'],
            'airline_code' => ['nullable', 'string', 'max:3', 'regex:/^[A-Za-z0-9]{2,3}$/'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'cabin' => ['required', Rule::in(['economy', 'premium_economy', 'business', 'first'])],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:2000'],
            'label' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['origin_airport'] = strtoupper($data['origin_airport']);
        $data['destination_airport'] = strtoupper($data['destination_airport']);
        $data['airline_code'] = strtoupper((string) ($data['airline_code'] ?? '')) ?: null;
        $data['currency'] = strtoupper($data['currency']);
        $data['price_minor'] = (int) round(((float) $data['price']) * 100);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['active'] = $request->boolean('active');
        unset($data['price'], $data['image']);

        return $data;
    }

    private function uniqueSlug(array $data, ?FlightOffer $ignore = null): string
    {
        $base = Str::slug($data['origin_airport'].'-'.$data['destination_airport'].'-'.$data['departure_date']);
        $slug = $base;
        $suffix = 2;

        while (FlightOffer::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
