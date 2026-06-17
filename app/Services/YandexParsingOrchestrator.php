<?php

namespace App\Services;

use App\Exceptions\ParseException;
use App\Models\Organization;
use App\Models\Proxy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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

        $maxAttempts = 20;
        $attempt = 0;
        $scrapedData = null;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            // Select a random active proxy (different each attempt) and check it before running
            // On the final attempt, we run without a proxy as a fallback
            $proxy = null;
            if ($attempt < $maxAttempts) {
                $proxy = $this->getWorkingProxy();
            }

            try {
                if ($proxy) {
                    Log::info("Attempt {$attempt}/{$maxAttempts}: Using proxy {$proxy->server} for parsing organization ID: {$organization->id}");
                } else {
                    Log::info("Attempt {$attempt}/{$maxAttempts}: Parsing without proxy for organization ID: {$organization->id}");
                }

                $scrapedData = $this->yandexPlaywrightClient->scrape($organization->url, $proxy);
                Log::info("Successfully parsed organization with ID: {$organization->id} on attempt {$attempt}");

                if ($proxy) {
                    $proxy->update([
                        'fails_count' => 0,
                        'last_used_at' => now(),
                    ]);
                }

                $this->organizationService->saveData($organization, $scrapedData);
                Log::info("Successfully saved organization with ID: {$organization->id}");

                return; // Scraped and saved successfully, exit the loop
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($proxy) {
                    $newFails = $proxy->fails_count + 1;
                    $proxy->update([
                        'fails_count' => $newFails,
                        'is_active' => $newFails < 3,
                        'last_used_at' => now(),
                    ]);
                    Log::warning("Attempt {$attempt} failed with proxy {$proxy->server}. Fails count: {$newFails}. Active: " . ($newFails < 3 ? 'yes' : 'no'));
                } else {
                    Log::warning("Attempt {$attempt} failed without proxy. Message: " . $e->getMessage());
                }
            }
        }

        // If all attempts failed
        $process = "processing";
        if ($lastException instanceof ParseException) {
            $process = "parsing";
        }
        if ($lastException instanceof QueryException) {
            $process = "saving";
        }

        Log::error("All {$maxAttempts} attempts failed. Failed $process organization with ID: {$organization->id}. Message: " . $lastException->getMessage());

        $this->organizationService->setFailedStatus($organization, $lastException->getMessage());

        throw $lastException;
    }

    /**
     * Get a random active proxy from the database and verify it is working.
     */
    protected function getWorkingProxy(): ?Proxy
    {
        $maxChecks = 30;
        $checked = 0;

        while ($checked < $maxChecks) {
            $proxy = Proxy::where('is_active', true)->inRandomOrder()->first();
            if (!$proxy) {
                return null;
            }

            $checked++;

            if ($this->testProxyConnection($proxy->server)) {
                return $proxy;
            }

            // If connection test failed, mark proxy as inactive immediately
            $proxy->update([
                'fails_count' => 3,
                'is_active' => false,
                'last_used_at' => now(),
            ]);
            
            Log::warning("Proxy {$proxy->server} failed pre-flight connection test. Deactivated.");
        }

        return null;
    }

    /**
     * Test connection to a proxy using a fast HTTP request.
     */
    protected function testProxyConnection(string $proxyUrl): bool
    {
        try {
            $response = Http::timeout(2)
                ->connectTimeout(2)
                ->withOptions([
                    'proxy' => $proxyUrl,
                    'verify' => false,
                ])
                ->get('https://ya.ru');

            if ($response->successful()) {
                $body = $response->body();
                return !str_contains($body, 'showcaptcha') && !str_contains($body, 'captcha');
            }
        } catch (\Throwable $e) {
            // Connection failed or timed out
        }

        return false;
    }
}
