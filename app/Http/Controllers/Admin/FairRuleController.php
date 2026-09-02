<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FairRule;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class FairRuleController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->string('sort')->toString(), ['airline_code', 'title', 'active', 'effective_from', 'created_at'], true)
            ? $request->string('sort')->toString()
            : 'airline_code';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $rules = FairRule::query()
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $query->where(fn ($query) => $query->where('airline_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('active', $request->string('status')->toString() === 'active'))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return view('admin.fair_rules.index', compact('rules'));
    }

    public function create(): View
    {
        return view('admin.fair_rules.create');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $rule = FairRule::create($data);
        $audit->record('fare_rule.created', "Created fare rule {$rule->title}.", $rule, after: $rule->toArray());

        return redirect()->route('admin.fair-rules.edit', $rule)->with('success', 'Fare rule created successfully.');
    }

    public function edit(FairRule $fair_rule): View
    {
        return view('admin.fair_rules.edit', ['rule' => $fair_rule]);
    }

    public function update(Request $request, FairRule $fair_rule, AuditLogger $audit): RedirectResponse
    {
        $before = $fair_rule->toArray();
        $data = $this->validated($request);
        $fair_rule->update($data);
        $audit->record('fare_rule.updated', "Updated fare rule {$fair_rule->title}.", $fair_rule, $before, $fair_rule->fresh()->toArray());

        return redirect()->route('admin.fair-rules.edit', $fair_rule)->with('success', 'Fare rule updated successfully.');
    }

    public function destroy(FairRule $fair_rule, AuditLogger $audit): RedirectResponse
    {
        $audit->record('fare_rule.deleted', "Deleted fare rule {$fair_rule->title}.", $fair_rule, $fair_rule->toArray());
        $fair_rule->delete();

        return redirect()->route('admin.fair-rules.index')->with('success', 'Fare rule deleted successfully.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'airline_code' => ['nullable', 'string', 'max:3', 'regex:/^[A-Za-z0-9]{2,3}$/'],
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:20000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_karossey_rule' => ['nullable', Rule::in(['0', '1'])],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $data['is_karossey_rule'] = $request->boolean('is_karossey_rule');
        $data['active'] = $request->boolean('active');
        $data['airline_code'] = $data['is_karossey_rule'] ? 'KAROSSY' : strtoupper((string) ($data['airline_code'] ?? ''));

        if (! $data['is_karossey_rule'] && $data['airline_code'] === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['airline_code' => 'Enter the airline IATA code for an airline-specific rule.']);
        }

        return $data;
    }
}
