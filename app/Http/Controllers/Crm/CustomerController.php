<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmTask;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['q'] ?? ''));
        $customers = Customer::query()->withCount('orders')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'contacts',
            'tags',
            'activities.actor',
            'tasks.assignee',
            'orders.bookings',
            'orders.payments',
        ]);

        return view('crm.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders->sortByDesc('created_at'),
            'recentActivities' => $customer->activities->take(12),
            'openTasks' => $customer->tasks->where('status', '!=', 'completed')->take(8),
        ]);
    }

    public function storeActivity(Request $request, Customer $customer): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:note,call,email,meeting,comment'],
            'subject' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        CrmActivity::create([...$validated, 'customer_id' => $customer->id, 'actor_user_id' => $request->user()->id, 'occurred_at' => now()]);

        return back()->with('success', 'Activity added to the customer timeline.');
    }

    public function storeTask(Request $request, Customer $customer): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        CrmTask::create([...$validated, 'customer_id' => $customer->id, 'created_by_user_id' => $request->user()->id, 'assigned_user_id' => $request->user()->id]);

        return back()->with('success', 'Follow-up task created.');
    }
}
