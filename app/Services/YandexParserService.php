<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class YandexParserService
{
    /**
     * Parse Yandex Maps organization details and reviews.
     *
     * @param Organization $organization
     * @return void
     * @throws Exception
     */
    public function parse(Organization $organization): void
    {
        $organization->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            // Spawn Node.js Playwright process
            // Playwright folder is at backend/parser/
            $scriptPath = base_path('parser/parse.js');
            
            $process = new Process([
                'node', 
                $scriptPath, 
                $organization->url
            ]);
            
            // Set working directory to the parser folder to ensure Node resolves modules
            $process->setWorkingDirectory(base_path('parser'));
            $process->setTimeout(180); // 3 minutes timeout for loading 600 reviews

            $process->run();

            if (!$process->isSuccessful()) {
                throw new Exception('Scraper process failed: ' . $process->getErrorOutput());
            }

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (!$result || !isset($result['success'])) {
                throw new Exception('Invalid scraper JSON output: ' . substr($output, 0, 500));
            }

            if (!$result['success']) {
                throw new Exception('Scraper reported failure: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Save details to database within transaction
            DB::transaction(function () use ($organization, $result) {
                $resolvedUrl = $result['resolvedUrl'] ?? $organization->url;
                
                // Extract unique Yandex ID from final redirected URL if matching pattern
                if (preg_match('/\/org\/(?:[^\/]+\/)?(\d+)/', $resolvedUrl, $matches)) {
                    $organization->yandex_id = $matches[1];
                }

                $orgInfo = $result['orgInfo'] ?? [];
                
                $organization->name = $orgInfo['name'] ?? $organization->name ?? 'Неизвестная организация';
                $organization->rating = isset($orgInfo['rating']) ? (float)$orgInfo['rating'] : $organization->rating;
                $organization->rating_count = isset($orgInfo['ratingCount']) ? (int)$orgInfo['ratingCount'] : $organization->rating_count;
                $organization->review_count = isset($orgInfo['reviewCount']) ? (int)$orgInfo['reviewCount'] : $organization->review_count;
                $organization->status = 'completed';
                $organization->error_message = null;
                $organization->last_parsed_at = now();
                $organization->save();

                // Replace cached reviews
                $organization->reviews()->delete();

                $reviewsData = [];
                $reviewsList = $result['reviews'] ?? [];

                foreach ($reviewsList as $review) {
                    $reviewsData[] = [
                        'organization_id' => $organization->id,
                        'author_name' => $review['authorName'] ?? 'Аноним',
                        'author_avatar' => $review['authorAvatar'] ?? null,
                        'rating' => isset($review['rating']) ? (int)$review['rating'] : 5,
                        'text' => $review['text'] ?? '',
                        'published_at_str' => $review['publishedAtStr'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Chunk insert reviews (100 at a time) for PostgreSQL efficiency
                foreach (array_chunk($reviewsData, 100) as $chunk) {
                    Review::insert($chunk);
                }
            });

            Log::info("Successfully parsed organization ID: {$organization->id}");

        } catch (Exception $e) {
            Log::error("Failed parsing organization ID: {$organization->id}. Error: " . $e->getMessage());
            
            $organization->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }
}
