<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('roles')->withCount('roles')
            ->when(request('q'), fn ($query, string $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => Role::orderBy('label')->get()]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->safe()->except(['password_confirmation', 'role_ids']);
        $user = User::create($data);
        $roleIds = match ($user->account_type) {
            'admin' => $request->validated('role_ids', []),
            'b2b' => Role::where('name', 'b2b-agent')->pluck('id')->all(),
            default => [],
        };
        $user->roles()->sync($roleIds);
        $audit->record('user.created', "Created {$user->account_type} user {$user->email}.", $user, after: $user->only(['name', 'email', 'account_type', 'company_name', 'currency_code', 'status']));

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }
}
