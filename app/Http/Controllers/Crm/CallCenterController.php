<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CallCenterCall;
use App\Models\CallCenterExtension;
use App\Models\CallCenterQueue;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CallCenterController extends Controller
{
    public function index(Request $request): View
    {
        $extension = $this->extensionFor($request);
        $this->ensureQueues();
        $todayCalls = CallCenterCall::query()->whereDate('started_at', today());
        $answered = (clone $todayCalls)->whereIn('status', ['answered', 'completed'])->count();
        $total = (clone $todayCalls)->count();

        return view('crm.calls.index', [
            'extension' => $extension,
            'queues' => CallCenterQueue::query()->withCount(['calls as waiting_calls_count' => fn ($query) => $query->where('status', 'queued')])->orderBy('name')->get(),
            'todayCallCount' => $total,
            'waitingCount' => CallCenterCall::query()->where('status', 'queued')->count(),
            'answeredRate' => $total ? round(($answered / $total) * 100) : null,
            'averageWait' => (int) CallCenterCall::query()->whereDate('started_at', today())->whereNotNull('answered_at')->avg('duration_seconds'),
            'recentCalls' => CallCenterCall::query()->with(['customer', 'agent', 'queue'])->latest('started_at')->limit(10)->get(),
            'customers' => Customer::query()->orderBy('first_name')->limit(100)->get(['id', 'first_name', 'last_name', 'email', 'phone']),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:available,away,dnd,offline']]);
        $this->extensionFor($request)->update(['status' => $validated['status']]);

        return back()->with('success', 'Call availability updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:inbound,outbound'],
            'phone_number' => ['required', 'string', 'max:50'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'outcome' => ['required', 'in:answered,follow_up,voicemail,no_answer,wrong_number,other'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        CallCenterCall::create([
            ...$validated,
            'agent_user_id' => $request->user()->id,
            'status' => 'completed',
            'started_at' => now(),
            'answered_at' => now(),
            'ended_at' => now(),
            'duration_seconds' => 0,
        ]);

        return back()->with('success', 'Call logged against the CRM record.');
    }

    private function extensionFor(Request $request): CallCenterExtension
    {
        return CallCenterExtension::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['extension' => 'K'.str_pad((string) $request->user()->id, 4, '0', STR_PAD_LEFT), 'status' => 'available'],
        );
    }

    private function ensureQueues(): void
    {
        foreach ([
            ['name' => 'Sales enquiries', 'slug' => 'sales-enquiries', 'department' => 'Sales'],
            ['name' => 'Booking support', 'slug' => 'booking-support', 'department' => 'Operations'],
            ['name' => 'Customer care', 'slug' => 'customer-care', 'department' => 'Support'],
        ] as $queue) {
            CallCenterQueue::firstOrCreate(['slug' => $queue['slug']], $queue);
        }
    }
}
