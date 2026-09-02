<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class VisaController extends Controller
{
    public function index()
    {
        $visas = Visa::orderBy('passport_country')->orderBy('country')->paginate(25);

        return view('admin.visas.index', compact('visas'));
    }

    public function create()
    {
        return view('admin.visas.create', ['visaTypes' => $this->visaTypes()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Visa::create($data);

        return redirect()->route('admin.visas.index')->with('success', 'Visa service created.');
    }

    public function edit(Visa $visa)
    {
        return view('admin.visas.edit', ['visa' => $visa, 'visaTypes' => $this->visaTypes()]);
    }

    public function update(Request $request, Visa $visa)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $visa);

        $visa->update($data);

        return redirect()->route('admin.visas.index')->with('success', 'Visa service updated.');
    }

    public function destroy(Visa $visa)
    {
        $visa->delete();

        return back()->with('success', 'Visa service removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'passport_country' => ['required', 'string', 'max:120'],
            'passport_country_code' => ['nullable', 'string', 'size:2'],
            'country' => ['required', 'string', 'max:120'],
            'destination_country_code' => ['nullable', 'string', 'size:2'],
            'visa_type' => ['required', 'in:evisa,sticker,consultation'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'validity' => ['required', 'string', 'max:180'],
            'processing_time' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'in:NGN,USD'],
            'fee_cents' => ['required', 'integer', 'min:0'],
            'consultation_fee_cents' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'requirements_text' => ['required', 'string', 'max:10000'],
            'important_information_text' => ['nullable', 'string', 'max:10000'],
            'active' => ['boolean'],
            'featured' => ['boolean'],
        ]);

        $data['passport_country_code'] = strtoupper((string) ($data['passport_country_code'] ?? '')) ?: null;
        $data['destination_country_code'] = strtoupper((string) ($data['destination_country_code'] ?? '')) ?: null;
        $data['consultation_fee_cents'] = (int) ($data['consultation_fee_cents'] ?? 0);
        $data['requirements_list'] = $this->lines($data['requirements_text']);
        $data['important_information'] = $this->lines($data['important_information_text'] ?? '');
        $data['requirements'] = implode("\n", $data['requirements_list']);
        unset($data['requirements_text'], $data['important_information_text']);

        return $data;
    }

    /** @return array<int, string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))->map(fn ($line) => trim((string) $line))->filter()->values()->all();
    }

    private function uniqueSlug(string $name, ?Visa $ignore = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;
        while (Visa::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, string> */
    private function visaTypes(): array
    {
        return ['evisa' => 'eVisa', 'sticker' => 'Sticker visa', 'consultation' => 'Consultation only'];
    }
}
