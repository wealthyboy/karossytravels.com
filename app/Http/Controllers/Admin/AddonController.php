<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AddonController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->string('type')->toString(), ['flight', 'hotel'], true) ? $request->string('type')->toString() : null;
        $sort = in_array($request->string('sort')->toString(), ['title', 'type', 'price_cents', 'active', 'created_at'], true) ? $request->string('sort')->toString() : 'title';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $addons = Addon::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($request->string('q')->toString(), fn ($query, string $search) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return view('admin.addons.index', compact('addons', 'type'));
    }

    public function create(Request $request): View
    {
        return view('admin.addons.create', ['type' => in_array($request->query('type'), ['flight', 'hotel'], true) ? $request->query('type') : 'flight']);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('addons', 'public');
        }
        $addon = Addon::create($data);
        $audit->record('addon.created', "Created {$addon->type} add-on {$addon->title}.", $addon, after: $addon->toArray());

        return redirect()->route('admin.addons.edit', $addon)->with('success', 'Add-on created successfully.');
    }

    public function edit(Addon $addon): View
    {
        return view('admin.addons.edit', ['addon' => $addon]);
    }

    public function update(Request $request, Addon $addon, AuditLogger $audit): RedirectResponse
    {
        $before = $addon->toArray();
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($addon->image_path) Storage::disk('public')->delete($addon->image_path);
            $data['image_path'] = $request->file('image')->store('addons', 'public');
        }
        $addon->update($data);
        $audit->record('addon.updated', "Updated {$addon->type} add-on {$addon->title}.", $addon, $before, $addon->fresh()->toArray());

        return redirect()->route('admin.addons.edit', $addon)->with('success', 'Add-on updated successfully.');
    }

    public function destroy(Addon $addon, AuditLogger $audit): RedirectResponse
    {
        $type = $addon->type;
        $audit->record('addon.deleted', "Deleted {$addon->type} add-on {$addon->title}.", $addon, $addon->toArray());
        if ($addon->image_path) Storage::disk('public')->delete($addon->image_path);
        $addon->delete();

        return redirect()->route('admin.addons.index', ['type' => $type])->with('success', 'Add-on deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['flight', 'hotel'])],
            'title' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'max:3072'],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $data['price_cents'] = (int) round(((float) $data['price']) * 100);
        $data['currency'] = strtoupper($data['currency']);
        $data['active'] = $request->boolean('active');
        unset($data['price'], $data['image']);

        return $data;
    }
}
