<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AnalyticsEventController extends Controller
{
    public function index(): View
    {
        $events = AnalyticsEvent::latest()->paginate(50);

        return view('admin.analytics.events.index', compact('events'));
    }

    public function show(AnalyticsEvent $event): View
    {
        return view('admin.analytics.events.show', compact('event'));
    }

    public function destroy(AnalyticsEvent $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.analytics.events.index')->with('success', 'Analytics event deleted.');
    }
}
