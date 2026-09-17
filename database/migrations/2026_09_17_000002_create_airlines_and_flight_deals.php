<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->char('code', 2)->unique();
            $table->string('country', 100);
            $table->char('country_code', 2)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        $airlines = [
            ['Qatar Airways', 'QR', 'Qatar', 'QA'], ['Emirates', 'EK', 'United Arab Emirates', 'AE'], ['Etihad Airways', 'EY', 'United Arab Emirates', 'AE'],
            ['British Airways', 'BA', 'United Kingdom', 'GB'], ['Virgin Atlantic', 'VS', 'United Kingdom', 'GB'], ['Lufthansa', 'LH', 'Germany', 'DE'],
            ['Air France', 'AF', 'France', 'FR'], ['KLM Royal Dutch Airlines', 'KL', 'Netherlands', 'NL'], ['Turkish Airlines', 'TK', 'Türkiye', 'TR'],
            ['Ethiopian Airlines', 'ET', 'Ethiopia', 'ET'], ['Kenya Airways', 'KQ', 'Kenya', 'KE'], ['RwandAir', 'WB', 'Rwanda', 'RW'],
            ['EgyptAir', 'MS', 'Egypt', 'EG'], ['Royal Air Maroc', 'AT', 'Morocco', 'MA'], ['South African Airways', 'SA', 'South Africa', 'ZA'],
            ['Air Peace', 'P4', 'Nigeria', 'NG'], ['United Nigeria Airlines', 'UN', 'Nigeria', 'NG'], ['Ibom Air', 'QI', 'Nigeria', 'NG'],
            ['Delta Air Lines', 'DL', 'United States', 'US'], ['United Airlines', 'UA', 'United States', 'US'], ['American Airlines', 'AA', 'United States', 'US'],
            ['Air Canada', 'AC', 'Canada', 'CA'], ['Royal Caribbean Airlines', 'BW', 'Trinidad and Tobago', 'TT'], ['LATAM Airlines', 'LA', 'Chile', 'CL'],
            ['Singapore Airlines', 'SQ', 'Singapore', 'SG'], ['Cathay Pacific', 'CX', 'Hong Kong', 'HK'], ['Japan Airlines', 'JL', 'Japan', 'JP'],
            ['All Nippon Airways', 'NH', 'Japan', 'JP'], ['Korean Air', 'KE', 'South Korea', 'KR'], ['Malaysia Airlines', 'MH', 'Malaysia', 'MY'],
            ['Thai Airways', 'TG', 'Thailand', 'TH'], ['Air India', 'AI', 'India', 'IN'], ['IndiGo', '6E', 'India', 'IN'],
            ['Saudia', 'SV', 'Saudi Arabia', 'SA'], ['Gulf Air', 'GF', 'Bahrain', 'BH'], ['Oman Air', 'WY', 'Oman', 'OM'],
            ['Kuwait Airways', 'KU', 'Kuwait', 'KW'], ['Royal Jordanian', 'RJ', 'Jordan', 'JO'], ['EL AL', 'LY', 'Israel', 'IL'],
            ['Swiss International Air Lines', 'LX', 'Switzerland', 'CH'], ['Austrian Airlines', 'OS', 'Austria', 'AT'], ['Brussels Airlines', 'SN', 'Belgium', 'BE'],
            ['Iberia', 'IB', 'Spain', 'ES'], ['TAP Air Portugal', 'TP', 'Portugal', 'PT'], ['Alitalia', 'AZ', 'Italy', 'IT'],
            ['Finnair', 'AY', 'Finland', 'FI'], ['SAS Scandinavian Airlines', 'SK', 'Denmark', 'DK'], ['LOT Polish Airlines', 'LO', 'Poland', 'PL'],
            ['Aer Lingus', 'EI', 'Ireland', 'IE'], ['ITA Airways', 'AZ', 'Italy', 'IT'], ['EVA Air', 'BR', 'Taiwan', 'TW'],
            ['China Southern Airlines', 'CZ', 'China', 'CN'], ['China Eastern Airlines', 'MU', 'China', 'CN'], ['Air China', 'CA', 'China', 'CN'],
            ['Qantas', 'QF', 'Australia', 'AU'], ['Virgin Australia', 'VA', 'Australia', 'AU'], ['Southwest Airlines', 'WN', 'United States', 'US'],
            ['JetBlue', 'B6', 'United States', 'US'], ['Alaska Airlines', 'AS', 'United States', 'US'], ['Allegiant Air', 'G4', 'United States', 'US'],
            ['Flydubai', 'FZ', 'United Arab Emirates', 'AE'], ['Air Arabia', 'G9', 'United Arab Emirates', 'AE'], ['Wizz Air', 'W6', 'Hungary', 'HU'],
            ['Ryanair', 'FR', 'Ireland', 'IE'], ['easyJet', 'U2', 'United Kingdom', 'GB'], ['Pegasus Airlines', 'PC', 'Türkiye', 'TR'],
        ];

        $now = now();
        DB::table('airlines')->insert(collect($airlines)->unique(fn (array $airline): string => $airline[1])->map(fn (array $airline): array => [
            'name' => $airline[0], 'code' => $airline[1], 'country' => $airline[2], 'country_code' => $airline[3], 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());

        Schema::create('flight_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->char('origin_airport', 3)->nullable();
            $table->char('destination_airport', 3)->nullable();
            $table->date('travel_from');
            $table->date('travel_until');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->char('discount_currency', 3)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['airline_id', 'travel_from', 'travel_until']);
            $table->index(['origin_airport', 'destination_airport']);
        });

        Schema::table('travel_offers', function (Blueprint $table): void {
            $table->foreignId('flight_deal_id')->nullable()->after('last_validated_at')->nullOnDelete();
            $table->unsignedBigInteger('deal_discount_minor')->default(0)->after('flight_deal_id');
        });
    }

    public function down(): void
    {
        Schema::table('travel_offers', function (Blueprint $table): void {
            $table->dropForeign(['flight_deal_id']);
            $table->dropColumn(['flight_deal_id', 'deal_discount_minor']);
        });
        Schema::dropIfExists('flight_deals');
        Schema::dropIfExists('airlines');
    }
};
