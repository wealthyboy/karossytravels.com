<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HolidayPackage;
use App\Travel\Pricing\DisplayCurrencyResolver;
use App\Travel\Pricing\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HolidayPackageController extends Controller
{
    public function index(Request $request, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): View
    {
        $currency = $resolver->resolve($request);
        $packages = HolidayPackage::query()->where('active', true)->orderByDesc('featured')->latest()->paginate(12);
        $packages->getCollection()->each(fn (HolidayPackage $package) => $package->setAttribute(
            'display_price',
            $rates->convertMinor($package->price_minor, $package->currency, $currency),
        ));

        return view('holidays.index', compact('packages'));
    }

    public function show(Request $request, HolidayPackage $holidayPackage, DisplayCurrencyResolver $resolver, ExchangeRateService $rates): View
    {
        abort_unless($holidayPackage->active, 404);
        $holidayPackage->setAttribute('display_price', $rates->convertMinor(
            $holidayPackage->price_minor,
            $holidayPackage->currency,
            $resolver->resolve($request),
        ));

        return view('holidays.show', compact('holidayPackage'));
    }
}
