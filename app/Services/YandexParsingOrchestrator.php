<?php

namespace App\Services;

use App\Exceptions\ParseException;
use App\Models\Organization;
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

        try {
            $scrapedData = $this->yandexPlaywrightClient->scrape($organization->url);
            Log::info("Successfully parsed organization with ID: {$organization->id}");

            $this->organizationService->saveData($organization, $scrapedData);
            Log::info("Successfully saved organization with ID: {$organization->id}");
        } catch (\Throwable $e) {
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
