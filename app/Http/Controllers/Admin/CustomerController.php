<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(): View
    {
        $sort = in_array(request('sort'), ['first_name', 'last_name', 'email', 'status', 'created_at'], true) ? request('sort') : 'created_at';
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';
        $customers = $this->visibleCustomers()->withCount('orders')
            ->when(request('q'), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")))
            ->when(request('status'), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View { return view('admin.customers.create'); }

    public function store(StoreCustomerRequest $request, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($request->validated() + ['owner_user_id' => $request->user()?->isB2b() ? $request->user()->id : null]);
        $audit->record('customer.created', "Created customer {$customer->email}.", $customer, after: $this->auditData($customer));

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ]], 201);
        }

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): View
    {
        $this->authorizeVisible($customer);
        return view('admin.customers.show', ['customer' => $customer->load(['orders' => fn ($query) => $query->latest()->limit(10)])]);
    }

    public function edit(Customer $customer): View { $this->authorizeVisible($customer); return view('admin.customers.edit', compact('customer')); }

    public function update(UpdateCustomerRequest $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeVisible($customer);
        $before = $this->auditData($customer);
        $data = $request->validated();
        if (blank($data['passport_number'] ?? null)) unset($data['passport_number']);
        $customer->update($data);
        $audit->record('customer.updated', "Updated customer {$customer->email}.", $customer, $before, $this->auditData($customer->refresh()));

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeVisible($customer);
        if ($customer->orders()->exists()) return back()->with('error', 'Customers with booking history cannot be deleted.');
        $audit->record('customer.deleted', "Deleted customer {$customer->email}.", $customer, $this->auditData($customer));
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function bulkDestroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['uuid', 'distinct', 'exists:customers,id']]);
        $customers = $this->visibleCustomers()->withCount('orders')->whereIn('id', $validated['ids'])->get();
        $deletable = $customers->where('orders_count', 0);
        $deleted = $deletable->pluck('email')->all();
        Customer::whereKey($deletable->pluck('id'))->delete();
        $audit->record('customer.bulk_deleted', 'Bulk deleted '.count($deleted).' customer(s).', after: ['deleted' => $deleted]);
        $skipped = $customers->count() - $deletable->count();

        return back()->with('success', count($deleted).' customer(s) deleted.'.($skipped ? " {$skipped} with booking history skipped." : ''));
    }

    /** @return array<string, mixed> */
    private function auditData(Customer $customer): array
    {
        return $customer->only(['title', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'nationality', 'country', 'company_name', 'status']);
    }

    private function visibleCustomers(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Customer::query();
        $user = request()->user();

        return $user?->isB2b() ? $query->where('owner_user_id', $user->id) : $query;
    }

    private function authorizeVisible(Customer $customer): void
    {
        $user = request()->user();
        abort_if($user?->isB2b() && $customer->owner_user_id !== $user->id, 404);
    }
}
