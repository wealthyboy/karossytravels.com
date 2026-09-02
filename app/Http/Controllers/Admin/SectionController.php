<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ServiceCatalog;
use App\Models\Booking;
use App\Models\FlightSearch;
use App\Models\HotelSearch;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TravelLog;
use App\Travel\TravelApi\TravelApiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class SectionController extends Controller
{
    public function customers(): View
    {
        return $this->section(
            'Customers',
            'Customer profiles, travellers, saved preferences and support history.',
            'No customer profiles yet',
            'Customer accounts will appear after authentication and onboarding are enabled.',
        );
    }

    public function admins(): View
    {
        return $this->section(
            'Administrators',
            'Manage administrators, operational roles and access permissions.',
            'No additional administrators yet',
            'Invite administrators and assign their roles from this page.',
        );
    }

    public function b2cOffers(): View
    {
        return $this->section(
            'B2C offers',
            'Create retail offers from live travel inventory for customers on the Karossy website and mobile app.',
            'No B2C offers yet',
            'Published retail fares, hotel deals, promotions and bundled products will appear here.',
        );
    }

    public function b2bOffers(): View
    {
        return $this->section(
            'B2B offers',
            'Create partner offers from live inventory with agency-specific pricing, markups and availability.',
            'No B2B offers yet',
            'Contracted fares, partner rates and reseller packages will appear here.',
        );
    }

    public function services(ServiceCatalog $catalog): View
    {
        return $this->section(
            'Travel services',
            'Control the products available across the website and mobile app.',
            data: ['services' => $catalog->all()],
        );
    }

    public function analytics(): View
    {
        $since = now()->subDays(30);
        $bookings = Booking::query()->where('created_at', '>=', $since);
        $bookingCount = (clone $bookings)->count();
        $searchCount = FlightSearch::query()->where('created_at', '>=', $since)->count()
            + HotelSearch::query()->where('created_at', '>=', $since)->count();
        $orders = Order::query()->where('created_at', '>=', $since)->where('status', 'confirmed');
        $revenueMinor = (int) (clone $orders)->sum('total_minor');

        return $this->section(
            'Executive overview',
            'Decision-ready demand, conversion, revenue and operational health for the last 30 days.',
            data: ['analytics' => [
                'revenue_minor' => $revenueMinor,
                'bookings' => $bookingCount,
                'searches' => $searchCount,
                'conversion' => $searchCount > 0 ? round(($bookingCount / $searchCount) * 100, 1) : 0,
                'tickets_issued' => Ticket::query()->whereNotNull('issued_at')->where('issued_at', '>=', $since)->count(),
                'average_booking_minor' => $bookingCount > 0 ? (int) round($revenueMinor / $bookingCount) : 0,
                'failed_api_calls' => TravelLog::query()->where('status', 'failed')->where('created_at', '>=', $since)->count(),
                'average_api_ms' => (int) round((float) (TravelLog::query()->where('created_at', '>=', $since)->avg('duration_ms') ?? 0)),
                'sources' => Booking::query()->selectRaw("COALESCE(source, 'unknown') as source_name, COUNT(*) as aggregate")
                    ->where('created_at', '>=', $since)->groupBy('source_name')->orderByDesc('aggregate')->get(),
                'top_routes' => FlightSearch::query()->selectRaw("CONCAT(origin, ' → ', destination) as route_name, COUNT(*) as aggregate")
                    ->where('created_at', '>=', $since)->groupBy('origin', 'destination')->orderByDesc('aggregate')->limit(5)->get(),
            ]],
        );
    }

    public function settings(): View
    {
        return $this->section(
            'Settings',
            'Company details, currencies, support contacts, markups and application preferences.',
            'Configuration foundation ready',
            'Editable settings will be introduced with administrator authentication and audit logs.',
        );
    }

    public function sabreProvider(TravelApiClient $travelApi): View
    {
        $configuration = (array) config('services.travel.travel_api', []);
        $travelApiStatus = $travelApi->status();
        $authScheme = (string) ($configuration['auth_scheme'] ?? 'oauth_client');
        $tokenPath = (string) ($configuration['token_path'] ?? '/v2/auth/token');
        $baseUrl = rtrim((string) ($travelApiStatus['base_url'] ?? ''), '/');
        $tokenEndpoint = $baseUrl !== '' ? $baseUrl.'/'.ltrim($tokenPath, '/') : $tokenPath;
        $recentLogs = TravelLog::query()->where('created_at', '>=', now()->subDay());

        return view('admin.providers.sabre', [
            'title' => 'Travel supplier',
            'description' => 'Monitor the airline and hotel supplier connection used by Karossy operations.',
            'providerStatus' => [
                'environment' => str((string) ($configuration['environment'] ?? 'cert'))->headline()->toString(),
                'enabled' => config('services.travel.provider') !== 'fake',
                'credentials_configured' => (bool) ($travelApiStatus['configured'] ?? false),
                'successful_calls' => (clone $recentLogs)->where('status', 'success')->count(),
                'failed_calls' => (clone $recentLogs)->where('status', 'failed')->count(),
                'last_activity' => TravelLog::query()->latest()->value('created_at'),
                'auth_scheme' => str($authScheme)->replace('_', ' ')->headline()->toString(),
                'access_token' => $travelApi->currentAccessToken(),
                'token_cached' => (bool) ($travelApiStatus['token_cached'] ?? false),
                'token_expires_at' => $travelApiStatus['token_expires_at'] ?? null,
                'token_endpoint' => $tokenEndpoint,
                'base_url' => $baseUrl,
            ],
            'providerTest' => session('providerConnectionTest'),
        ]);
    }

    public function testSabreProvider(TravelApiClient $travelApi): RedirectResponse
    {
        $startedAt = microtime(true);

        try {
            $travelApi->authenticate(true);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            return redirect()->route('admin.providers.sabre')->with('providerConnectionTest', [
                'status' => 'success',
                'duration_ms' => $durationMs,
                'message' => 'Sabre authentication succeeded. Karossy received a fresh access token from the supplier token endpoint.',
            ]);
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            report($exception);

            return redirect()->route('admin.providers.sabre')->with('providerConnectionTest', [
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Sabre authentication failed without an error message. Review the application log for the underlying exception.',
            ]);
        }
    }

    public function workspace(Request $request, string $section, string $page): View
    {
        $navigation = collect(config('admin_navigation'));
        $group = $navigation->firstWhere('slug', $section);
        abort_unless($group, 404);

        $item = collect($group['items'])->firstWhere('slug', $page);
        abort_unless($item, 404);

        $user = $request->user();
        abort_unless(app()->isLocal() || $user?->hasPermission($group['permission']), 403);

        // Provide booking lists for the workspace booking pages so admin links show records
        $data = [];
        if ($section === 'bookings') {
            if ($page === 'flights') {
                $data['bookings'] = Booking::with('order')
                    ->where('product_type', 'flight')
                    ->orderBy('created_at', 'desc')
                    ->take(50)
                    ->get();
            } elseif ($page === 'all') {
                $data['bookings'] = Booking::with('order')
                    ->orderBy('created_at', 'desc')
                    ->take(100)
                    ->get();
            } elseif ($page === 'sources') {
                $data['sourceStats'] = Booking::query()
                    ->selectRaw("COALESCE(source, 'unknown') as source_name, COUNT(*) as bookings_count")
                    ->groupBy('source_name')->orderByDesc('bookings_count')->get();
                $data['campaignStats'] = Booking::query()
                    ->selectRaw("COALESCE(utm_source, source, 'unknown') as campaign_source, COUNT(*) as bookings_count")
                    ->groupBy('campaign_source')->orderByDesc('bookings_count')->limit(20)->get();
            }
        }

        return $this->section(
            $item['label'],
            $group['description'] ?? "Manage {$group['label']} operations from this workspace.",
            "No {$item['label']} data yet",
            'The tools, filters and records for this area will appear here as its backend module is implemented.',
            data: $data,
        );
    }

    /** @param array<string, mixed> $data */
    private function section(
        string $title,
        string $description,
        ?string $emptyTitle = null,
        ?string $emptyDescription = null,
        array $data = [],
    ): View {
        return view('admin.section', [
            'title' => $title,
            'description' => $description,
            'emptyTitle' => $emptyTitle,
            'emptyDescription' => $emptyDescription,
            ...$data,
        ]);
    }
}
