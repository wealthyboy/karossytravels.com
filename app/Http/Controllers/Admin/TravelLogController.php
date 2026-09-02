<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TravelLogController extends Controller
{
    public function index(Request $request, string $product): View
    {
        abort_unless(in_array($product, ['all', 'flight', 'hotel'], true), 404);

        $logs = TravelLog::query()
            ->with('user:id,name,email')
            ->when($product !== 'all', fn ($query) => $query->where('product_type', $product))
            ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->string('stage')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.travel-logs.index', compact('logs', 'product'));
    }

    public function show(TravelLog $travelLog): View
    {
        return view('admin.travel-logs.show', ['log' => $travelLog->load('user:id,name,email')]);
    }
}
