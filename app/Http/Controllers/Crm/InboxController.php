<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

final class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'in:email,sms,whatsapp,website,in_app'],
            'status' => ['nullable', 'in:open,pending,closed'],
        ]);

        $conversations = CrmConversation::query()
            ->with(['customer', 'booking.order', 'assignee', 'messages' => fn (HasMany $messages) => $messages->latest()->limit(1)])
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('crm.inbox.index', [
            'conversations' => $conversations,
            'summary' => [
                'open' => CrmConversation::query()->where('status', 'open')->count(),
                'pending' => CrmConversation::query()->where('status', 'pending')->count(),
                'unassigned' => CrmConversation::query()->whereNull('assigned_user_id')->whereIn('status', ['open', 'pending'])->count(),
            ],
        ]);
    }
}
