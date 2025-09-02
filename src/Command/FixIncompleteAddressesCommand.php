<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixIncompleteAddressesCommand extends Command
{
    protected $signature = 'address:fix-incomplete';

    protected $description = 'Fix incomplete addresses in the database.';

    public function handle()
    {
        $processedNumber = 0;
        $addressesFound = 0;

        // First, fill in lat/lng for addresses that have other entries with the same dedupe_hash
        $this->updateAddressesThatHaveInfoInSameGroupOfHashes();

        // Get count of addresses that still need info
        $remainingCount = Address::where(fn($q) => $q->whereNull('lat')->orWhereNull('lng')->orWhere('lat', 0)->orWhere('lng', 0))
            ->whereNotNull('address1')
            ->where('address1', '!=', '')
            ->count();

        $groupedCount = Address::where(fn($q) => $q->whereNull('lat')->orWhereNull('lng')->orWhere('lat', 0)->orWhere('lng', 0))
            ->whereNotNull('address1')
            ->where('address1', '!=', '')
            ->selectRaw('COUNT(distinct dedupe_hash) as count')
            ->first();

        $this->info("Updated addresses with available coordinate data.");
        $this->info("Addresses still missing coordinates: $remainingCount. ($groupedCount->count unique addresses)");
        $this->info("Going through geocoding for remaining addresses...");

        Address::select('dedupe_hash')
            ->where(function ($query) {
                $query->whereNull('lat')->orWhereNull('lng')
                    ->orWhere('lat', 0)->orWhere('lng', 0);
            })
            ->whereNotNull('address1')
            ->where('address1', '!=', '')
            ->groupBy('dedupe_hash')
            ->havingRaw('MIN(COALESCE(DATE(updated_at), "1970-01-01")) < DATE(NOW())')
            ->orderBy('dedupe_hash', 'desc')
            ->chunk(100, function ($addresses) use (&$processedNumber, &$addressesFound) {
                if (geocodingService()->acceptsBatch()) {
                    $this->manageBatch($addresses, $addressesFound);
                } else {
                    $this->manageNonBatch($addresses, $addressesFound);
                }

                $processedNumber += count($addresses);
                $this->info("Processed $processedNumber addresses. $addressesFound addresses found.");
            });
    }

    protected function manageBatch($addresses, &$addressesFound)
    {
        $addressStrings = [];
        $addressMap = [];

        foreach ($addresses as $address) {
            $addressModel = Address::where('dedupe_hash', $address->dedupe_hash)->first();
            $addressString = implode(', ', $addressModel->getAddressToGeocode());
            $addressStrings[] = $addressString;
            $addressMap[$addressString] = $addressModel;
        }

        /**
         * @var \Condoedge\Utils\Services\Maps\GeocodingBatchService $geocodingService
         */
        $geocodingService = geocodingService();
        $coordinatesBatch = $geocodingService->geocodeBatch($addressStrings);

        foreach ($coordinatesBatch as $addressString => $coordinates) {
            $addressModel = $addressMap[$addressString];

            if ($coordinates) {
                $addressesFound++;

                DB::table('addresses')
                    ->where('dedupe_hash', $addressModel->dedupe_hash)
                    ->update([
                        'lat' => $coordinates->getLatitude(),
                        'lng' => $coordinates->getLongitude(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('addresses')
                    ->where('dedupe_hash', $addressModel->dedupe_hash)
                    ->update([
                        'lat' => null,
                        'lng' => null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    protected function manageNonBatch($addresses, &$addressesFound)
    {
        foreach ($addresses as $address) {
            $address = Address::where('dedupe_hash', $address->dedupe_hash)->first();
            $coordinates = geocodingService()->geocode($address->getAddressToGeocode());

            if ($coordinates) {
                $addressesFound++;

                DB::table('addresses')
                    ->where('dedupe_hash', $address->dedupe_hash)
                    ->update([
                        'lat' => $coordinates->getLatitude(),
                        'lng' => $coordinates->getLongitude(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('addresses')
                    ->where('dedupe_hash', $address->dedupe_hash)
                    ->update([
                        'lat' => null,
                        'lng' => null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    protected function updateAddressesThatHaveInfoInSameGroupOfHashes()
    {
        // We find addresses that have lat/lng info in the same dedupe_hash group and update the incomplete ones with that info
        // The dedupe_hash is a quick way to group equal addresses based on address, state, city, postal code
        DB::statement("
            UPDATE addresses a1
            JOIN (
                SELECT 
                    dedupe_hash, 
                    MAX(lat) as lat, 
                    MAX(lng) as lng
                FROM addresses 
                WHERE lat IS NOT NULL 
                AND lng IS NOT NULL 
                AND lat != 0 
                AND lng != 0
                GROUP BY dedupe_hash
            ) a2 ON a1.dedupe_hash = a2.dedupe_hash
            SET 
                a1.lat = a2.lat,
                a1.lng = a2.lng,
                a1.updated_at = NOW()
            WHERE (a1.lat IS NULL OR a1.lng IS NULL OR a1.lat = 0 OR a1.lng = 0) 
            AND a1.address1 IS NOT NULL and a1.address1 != ''
        ");
    }
}
