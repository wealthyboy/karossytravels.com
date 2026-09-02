<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HolidayPackage;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class HolidayPackageController extends Controller
{
    public function index(): View
    {
        return view('admin.holidays.index', ['packages' => HolidayPackage::latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.holidays.create');
    }

    public function edit(HolidayPackage $holidayPackage): View
    {
        return view('admin.holidays.edit', compact('holidayPackage'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('holidays', 'public');
        }
        $package = HolidayPackage::create($data);
        $audit->record('holiday.created', "Created holiday package {$package->title}.", $package, after: $package->toArray());

        return redirect()->route('admin.holidays.edit', $package)->with('success', 'Holiday package created.');
    }

    public function update(Request $request, HolidayPackage $holidayPackage, AuditLogger $audit): RedirectResponse
    {
        $before = $holidayPackage->toArray();
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title'], $holidayPackage);
        if ($request->hasFile('image')) {
            if ($holidayPackage->image_path) {
                Storage::disk('public')->delete($holidayPackage->image_path);
            } $data['image_path'] = $request->file('image')->store('holidays', 'public');
        }
        $holidayPackage->update($data);
        $audit->record('holiday.updated', "Updated holiday package {$holidayPackage->title}.", $holidayPackage, $before, $holidayPackage->fresh()->toArray());

        return back()->with('success', 'Holiday package updated.');
    }

    public function destroy(HolidayPackage $holidayPackage, AuditLogger $audit): RedirectResponse
    {
        $audit->record('holiday.deleted', "Deleted holiday package {$holidayPackage->title}.", $holidayPackage, $holidayPackage->toArray());
        if ($holidayPackage->image_path) {
            Storage::disk('public')->delete($holidayPackage->image_path);
        } $holidayPackage->delete();

        return back()->with('success', 'Holiday package removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'destination' => ['required', 'string', 'max:160'], 'country' => ['nullable', 'string', 'max:120'], 'tagline' => ['nullable', 'string', 'max:180'], 'summary' => ['nullable', 'string', 'max:4000'], 'nights' => ['required', 'integer', 'min:1', 'max:365'], 'days' => ['required', 'integer', 'min:2', 'max:366'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'in:NGN,USD'], 'image' => ['nullable', 'image', 'max:5120'], 'inclusions_text' => ['nullable', 'string', 'max:10000'], 'featured' => ['nullable', 'boolean'], 'active' => ['nullable', 'boolean']]);
        $data['price_minor'] = (int) round(((float) $data['price']) * 100);
        $data['inclusions'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['inclusions_text'] ?? '')))->map(fn ($line) => trim((string) $line))->filter()->values()->all();
        $data['featured'] = $request->boolean('featured');
        $data['active'] = $request->boolean('active');
        unset($data['price'],$data['image'],$data['inclusions_text']);

        return $data;
    }

    private function slug(string $title, ?HolidayPackage $ignore = null): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        while (HolidayPackage::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

return $slug;
    }
}
