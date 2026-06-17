<?php

namespace App\Services;

use App\Exceptions\ParseException;
use App\Models\Organization;
use App\Models\Proxy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Exception;

class YandexParsingOrchestrator
{
    protected YandexPlaywrightClient $yandexPlaywrightClient;
    protected OrganizationService $organizationService;

    public function __construct(
        YandexPlaywrightClient $yandexPlaywrightClient,
        OrganizationService $organizationService
    ) {
        $this->yandexPlaywrightClient = $yandexPlaywrightClient;
        $this->organizationService = $organizationService;
    }

    /**
     * Parse Yandex Maps organization details and reviews and save it to the database.
     *
     * @throws Exception
     */
    public function parse(Organization $organization): void
    {
        $this->organizationService->setProcessingStatus($organization);

        // Select a random active proxy from the database (if any exist)
        $proxy = Proxy::where('is_active', true)->inRandomOrder()->first();

        try {
            if ($proxy) {
                Log::info("Using proxy {$proxy->server} for parsing organization ID: {$organization->id}");
            } else {
                Log::info("No active proxies available in DB. Parsing without proxy.");
            }

            $scrapedData = $this->yandexPlaywrightClient->scrape($organization->url, $proxy);
            Log::info("Successfully parsed organization with ID: {$organization->id}");

            // Reset fails count on successful parse
            if ($proxy) {
                $proxy->update([
                    'fails_count' => 0,
                    'last_used_at' => now(),
                ]);
            }

            $this->organizationService->saveData($organization, $scrapedData);
            Log::info("Successfully saved organization with ID: {$organization->id}");
        } catch (\Throwable $e) {
            // Track proxy failures and deactivate if failed 3 times
            if ($proxy) {
                $newFails = $proxy->fails_count + 1;
                $proxy->update([
                    'fails_count' => $newFails,
                    'is_active' => $newFails < 3,
                    'last_used_at' => now(),
                ]);
                Log::warning("Proxy {$proxy->server} failed. Fails count: {$newFails}. Active: " . ($newFails < 3 ? 'yes' : 'no'));
            }

            $process = "processing";
            if ($e instanceof ParseException) {
                $process = "parsing";
            }
            if ($e instanceof QueryException) {
                $process = "saving";
            }

            Log::error("Failed $process organization with ID: {$organization->id}. Message: " . $e->getMessage());

            $this->organizationService->setFailedStatus($organization, $e->getMessage());

            throw $e;
        }
    }
}
