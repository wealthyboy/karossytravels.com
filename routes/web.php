<?php

use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\AnalyticsEventController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CurrencySettingController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FairRuleController;
use App\Http\Controllers\Admin\FlightOrderController;
use App\Http\Controllers\Admin\FlightOfferController;
use App\Http\Controllers\Admin\FlightRevalidationController;
use App\Http\Controllers\Admin\FlightSearchController;
use App\Http\Controllers\Admin\FlightSearchPageController;
use App\Http\Controllers\Admin\HolidayPackageController as AdminHolidayPackageController;
use App\Http\Controllers\Admin\HotelOrderController;
use App\Http\Controllers\Admin\HotelSearchPageController;
use App\Http\Controllers\Admin\PartnerEnquiryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PricingSettingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\TravelLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisaApplicationController;
use App\Http\Controllers\Admin\VisaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Web\AccountBookingController;
use App\Http\Controllers\Web\CarPartnerController;
use App\Http\Controllers\Web\FlightCheckoutController;
use App\Http\Controllers\Web\FlightResultsController;
use App\Http\Controllers\Web\FlightRevalidationController as WebFlightRevalidationController;
use App\Http\Controllers\Web\FlightReviewController;
use App\Http\Controllers\Web\FlightSearchController as WebFlightSearchController;
use App\Http\Controllers\Web\HolidayPackageController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\HotelResultsController;
use App\Http\Controllers\Web\HotelRoomsController;
use App\Http\Controllers\Web\HotelCheckoutController;
use App\Http\Controllers\Web\ManageBookingController;
use App\Http\Controllers\Web\PaystackWebhookController;
use App\Http\Controllers\Web\VisaServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/', HomeController::class)->name('home');
Route::get('/visas', [VisaServiceController::class, 'index'])->name('visas.index');
Route::get('/visas/results', [VisaServiceController::class, 'results'])->name('visas.results');
Route::post('/visas/applications/{application}/payment/verify', [VisaServiceController::class, 'verify'])->middleware('throttle:20,1')->name('visas.payment.verify');
Route::get('/visas/applications/{application}/complete', [VisaServiceController::class, 'complete'])->name('visas.complete');
Route::get('/visas/{visa}/checkout', [VisaServiceController::class, 'checkout'])->name('visas.checkout');
Route::post('/visas/{visa}/payment/initialize', [VisaServiceController::class, 'initialize'])->middleware('throttle:8,1')->name('visas.payment.initialize');
Route::get('/visas/{visa}', [VisaServiceController::class, 'show'])->name('visas.show');
Route::get('/cars/partner-with-us', [CarPartnerController::class, 'index'])->name('cars.partners');
Route::post('/cars/partner-with-us', [CarPartnerController::class, 'store'])->middleware('throttle:8,1')->name('cars.partners.store');
Route::get('/holidays', [HolidayPackageController::class, 'index'])->name('holidays.index');
Route::get('/holidays/{holidayPackage}', [HolidayPackageController::class, 'show'])->name('holidays.show');
Route::view('/student-study-program', 'study-program')->name('study-program');
Route::get('/manage-booking', [ManageBookingController::class, 'index'])->name('manage-booking.index');
Route::post('/manage-booking', [ManageBookingController::class, 'lookup'])->middleware('throttle:10,1')->name('manage-booking.lookup');
Route::get('/manage-booking/{booking}', [ManageBookingController::class, 'show'])
    ->whereUuid('booking')->middleware('signed')->name('manage-booking.show');
Route::get('/flights/results', FlightResultsController::class)->middleware('throttle:30,1')->name('flights.results');
Route::post('/flights/search', WebFlightSearchController::class)->middleware('throttle:30,1')->name('flights.search.store');
Route::get('/hotels/results', HotelResultsController::class)->middleware('throttle:30,1')->name('hotels.results');
Route::post('/hotels/search', [HotelResultsController::class, 'search'])->middleware('throttle:30,1')->name('hotels.search.store');
Route::get('/hotels/offers/{offer}/rooms', HotelRoomsController::class)->name('hotels.rooms');
Route::get('/hotels/offers/{offer}/checkout', [HotelCheckoutController::class, 'show'])->name('hotels.checkout');
Route::post('/hotels/offers/{offer}/checkout/payment', [HotelCheckoutController::class, 'initialize'])->middleware('throttle:5,1')->name('hotels.checkout.payment');
Route::post('/hotels/offers/{offer}/checkout/verify', [HotelCheckoutController::class, 'verify'])->middleware('throttle:30,1')->name('hotels.checkout.verify');
Route::get('/hotels/checkout/complete/{order}', [HotelCheckoutController::class, 'complete'])->name('hotels.checkout.complete');
Route::get('/flights/review/{offer}', FlightReviewController::class)->name('flights.review');
Route::post('/flights/offers/{offer}/revalidate', WebFlightRevalidationController::class)
    ->middleware('throttle:20,1')->name('flights.offers.revalidate');
Route::get('/checkout/{offer}/travellers', [FlightCheckoutController::class, 'travellers'])->name('checkout.travellers');
Route::post('/checkout/{offer}/travellers', [FlightCheckoutController::class, 'storeTravellers'])->name('checkout.travellers.store');
Route::get('/checkout/{offer}/payment', [FlightCheckoutController::class, 'payment'])->name('checkout.payment');
Route::post('/checkout/{offer}/payment/initialize', [FlightCheckoutController::class, 'confirm'])
    ->middleware('throttle:5,1')->name('checkout.payment.initialize');
Route::post('/checkout/{offer}/payment/verify', [FlightCheckoutController::class, 'verifyPayment'])
    ->middleware('throttle:30,1')->name('checkout.payment.verify');
Route::get('/checkout/complete/{order}', [FlightCheckoutController::class, 'complete'])->name('checkout.complete');
Route::post('/webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');
Route::middleware('auth')->group(function (): void {
    Route::get('/account/bookings', [AccountBookingController::class, 'index'])->name('account.bookings.index');
    Route::get('/account/bookings/{booking}', [AccountBookingController::class, 'show'])->whereUuid('booking')->name('account.bookings.show');
});
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:registration')->name('register.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/currency', function (Request $request) {
    $supported = array_map('strtoupper', config('travel.currency.supported', ['NGN', 'USD']));
    $validated = $request->validate(['currency' => ['required', Rule::in($supported)]]);
    $request->session()->put('display_currency', $validated['currency']);
    if ($request->user()) {
        $request->user()->forceFill(['currency_code' => $validated['currency']])->save();
    }

    return back();
})->name('currency.update');

Route::prefix('admin')->name('admin.')->middleware('admin.hidden')->group(function (): void {
    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/flights/search', FlightSearchPageController::class)->middleware('permission:bookings.view')->name('flights.search');
    Route::resource('flight-offers', FlightOfferController::class)->except('show')->middleware('permission:offers.manage');
    Route::post('/flights/search', FlightSearchController::class)->middleware(['permission:bookings.view', 'throttle:30,1'])->name('flights.search.store');
    Route::post('/flights/offers/{offer}/revalidate', FlightRevalidationController::class)->middleware(['permission:bookings.manage', 'throttle:20,1'])->name('flights.offers.revalidate');
    Route::get('/flights/offers/{offer}/book', [FlightOrderController::class, 'create'])->middleware('permission:bookings.manage')->name('flights.orders.create');
    Route::post('/flights/offers/{offer}/book', [FlightOrderController::class, 'store'])->middleware(['permission:bookings.manage', 'throttle:10,1'])->name('flights.orders.store');
    Route::get('/flights/orders/{order}', [FlightOrderController::class, 'show'])->middleware('permission:bookings.view')->name('flights.orders.show');
    Route::get('/hotels/search', [HotelSearchPageController::class, 'index'])->middleware('permission:bookings.view')->name('hotels.search');
    Route::get('/hotels/results', [HotelSearchPageController::class, 'results'])->middleware(['permission:bookings.view', 'throttle:30,1'])->name('hotels.results');
    Route::get('/hotels/offers/{offer}/book', [HotelOrderController::class, 'create'])->middleware('permission:bookings.manage')->name('hotels.orders.create');
    Route::post('/hotels/offers/{offer}/book', [HotelOrderController::class, 'store'])->middleware(['permission:bookings.manage', 'throttle:10,1'])->name('hotels.orders.store');
    Route::get('/bookings', [BookingController::class, 'index'])->middleware('permission:bookings.view')->name('bookings.index');
    Route::get('/bookings/flights', [BookingController::class, 'flights'])->middleware('permission:bookings.view')->name('bookings.flights');
    Route::get('/bookings/hotels', [BookingController::class, 'hotels'])->middleware('permission:bookings.view')->name('bookings.hotels');
    Route::get('/bookings/visas', [BookingController::class, 'visas'])->middleware('permission:bookings.view')->name('bookings.visas');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->whereUuid('booking')->middleware('permission:bookings.view')->name('bookings.show');
    Route::post('/bookings/{booking}/modify', [BookingController::class, 'modify'])->middleware('permission:bookings.manage')->name('bookings.modify');
    Route::post('/bookings/{booking}/void', [BookingController::class, 'voidTickets'])->middleware('permission:bookings.manage')->name('bookings.void');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('permission:bookings.manage')->name('bookings.cancel');
    Route::get('/users/admins', [SectionController::class, 'admins'])->middleware('permission:team.manage')->name('users.admins');
    Route::redirect('/users/customers', '/admin/customers')->middleware('permission:customers.view')->name('users.customers');
    Route::delete('/customers/bulk', [CustomerController::class, 'bulkDestroy'])->middleware('permission:customers.manage')->name('customers.bulk-destroy');
    Route::resource('customers', CustomerController::class)->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('permission:customers.manage');
    Route::resource('customers', CustomerController::class)->only(['index', 'show'])->middleware('permission:customers.view');
    Route::redirect('/offers', '/admin/offers/b2c')->middleware('permission:offers.view')->name('offers.index');
    Route::get('/offers/b2c', [SectionController::class, 'b2cOffers'])->middleware('permission:offers.view')->name('offers.b2c');
    Route::get('/offers/b2b', [SectionController::class, 'b2bOffers'])->middleware('permission:offers.view')->name('offers.b2b');
    Route::get('/services', [SectionController::class, 'services'])->middleware('permission:services.view')->name('services.index');
    Route::get('/analytics', [SectionController::class, 'analytics'])->middleware('permission:analytics.view')->name('analytics.index');
    Route::get('/providers/sabre', [SectionController::class, 'sabreProvider'])->middleware('permission:integrations.view')->name('providers.sabre');
    Route::get('/settings', [SectionController::class, 'settings'])->middleware('permission:settings.manage')->name('settings.index');
    Route::get('/pricing/{product}', [PricingSettingController::class, 'edit'])->whereIn('product', ['airline', 'hotel'])->middleware('permission:offers.manage')->name('pricing.edit');
    Route::put('/pricing/{product}', [PricingSettingController::class, 'update'])->whereIn('product', ['airline', 'hotel'])->middleware('permission:offers.manage')->name('pricing.update');
    Route::get('/settings/currency', [CurrencySettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.currency.edit');
    Route::put('/settings/currency', [CurrencySettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.currency.update');
    Route::post('/settings/currency/refresh', [CurrencySettingController::class, 'refresh'])->middleware(['permission:settings.manage', 'throttle:6,1'])->name('settings.currency.refresh');
    Route::delete('permissions/bulk', [PermissionController::class, 'bulkDestroy'])->middleware('permission:team.manage')->name('permissions.bulk-destroy');
    Route::resource('permissions', PermissionController::class)
        ->except('show')
        ->middleware('permission:team.manage');
    Route::resource('fair-rules', FairRuleController::class)->except(['show'])->middleware('permission:bookings.manage');
    Route::resource('addons', AddonController::class)->except(['show'])->middleware('permission:offers.manage');
    Route::resource('visas', VisaController::class)->except(['show'])->middleware('permission:services.manage');
    Route::get('/visa-applications', [VisaApplicationController::class, 'index'])->middleware('permission:services.view')->name('visa-applications.index');
    Route::get('/visa-applications/{visaApplication}', [VisaApplicationController::class, 'show'])->middleware('permission:services.view')->name('visa-applications.show');
    Route::put('/visa-applications/{visaApplication}', [VisaApplicationController::class, 'update'])->middleware('permission:services.manage')->name('visa-applications.update');
    Route::resource('holidays', AdminHolidayPackageController::class)->except(['show'])->middleware('permission:services.manage');
    Route::get('/partner-enquiries', [PartnerEnquiryController::class, 'index'])->middleware('permission:services.view')->name('partner-enquiries.index');
    Route::get('/partner-enquiries/{partnerEnquiry}', [PartnerEnquiryController::class, 'show'])->middleware('permission:services.view')->name('partner-enquiries.show');
    Route::put('/partner-enquiries/{partnerEnquiry}', [PartnerEnquiryController::class, 'update'])->middleware('permission:services.manage')->name('partner-enquiries.update');
    Route::resource('analytics/events', AnalyticsEventController::class)
        ->only(['index', 'show', 'destroy'])
        ->names(['index' => 'analytics.events.index', 'show' => 'analytics.events.show', 'destroy' => 'analytics.events.destroy'])
        ->middleware('permission:analytics.view');
    Route::get('/travel-logs/{product}', [TravelLogController::class, 'index'])->whereIn('product', ['all', 'flight', 'hotel', 'visa', 'car'])->middleware('permission:integrations.view')->name('travel-logs.index');
    Route::get('/travel-log/{travelLog}', [TravelLogController::class, 'show'])->middleware('permission:integrations.view')->name('travel-logs.show');
    Route::delete('roles/bulk', [RoleController::class, 'bulkDestroy'])->middleware('permission:team.manage')->name('roles.bulk-destroy');
    Route::resource('roles', RoleController::class)
        ->except('show')
        ->middleware('permission:team.manage');
    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store'])
        ->middleware('permission:team.manage');
    Route::get('/workspace/{section}/{page}', [SectionController::class, 'workspace'])
        ->where(['section' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+'])
        ->name('workspace');
});
