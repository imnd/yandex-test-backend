<?php

namespace App\Services;

use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Get the current active organization.
     */
    public function getActiveOrganization(): Organization
    {
        return Organization::firstOrFail();
    }

    /**
     * Save the Yandex Maps organization URL and trigger parsing.
     */
    public function saveSettings(string $url): Organization
    {
        $yandexId = $this->extractYandexId($url);
        if ($yandexId) {
            // Normalize URL to direct organization card for faster and more reliable parsing
            $url = "https://yandex.ru/maps/org/{$yandexId}/";
        } else {
            $yandexId = 'pending_' . bin2hex(random_bytes(8));
        }

        // Delete all other organizations to keep only one active dashboard
        Organization::query()->delete();

        // Create new organization entry
        $organization = Organization::create([
            'yandex_id' => $yandexId,
            'url' => $url,
            'status' => 'pending',
            'error_message' => null,
        ]);

        // Dispatch background queue job
        ParseOrganizationJob::dispatch($organization);

        return $organization;
    }

    /**
     * Get reviews for the active organization (paginated, 50 per page).
     */
    public function getReviews(int $perPage = 50): ?LengthAwarePaginator
    {
        return Organization::firstOrFail()
            ->reviews()
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function refreshReviews(): ?Organization
    {
        $organization = Organization::firstOrFail();

        $yandexId = $this->extractYandexId($organization->url);
        if ($yandexId) {
            $normalizedUrl = "https://yandex.ru/maps/org/{$yandexId}/";
            if ($organization->url !== $normalizedUrl) {
                $organization->update(['url' => $normalizedUrl]);
            }
        }

        $organization->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ParseOrganizationJob::dispatch($organization);

        return $organization;
    }

    /**
     * Set the organization status to processing.
     */
    public function setProcessingStatus(Organization $organization): void
    {
        $organization->update([
            'status' => 'processing',
            'error_message' => null,
        ]);
    }

    /**
     * Set the organization status to failed.
     */
    public function setFailedStatus(Organization $organization, string $errorMessage): void
    {
        $organization->update([
            'status' => 'failed',
            'error_message' => substr($errorMessage, 0, 1000),
        ]);
    }

    /**
     * Save details to the database within a database transaction.
     *
     * @throws QueryException
     */
    public function saveData(Organization $organization, array $result): void
    {
        $yandexId = $organization->yandex_id;
        if (isset($result['resolvedUrl'])) {
            $yandexId = $this->extractYandexId($result['resolvedUrl']);
        } else if (empty($yandexId) && $organization->url) {
            $yandexId = $this->extractYandexId($organization->url);
        }

        $orgInfo = $result['orgInfo'] ?? [];

        $organization->fill([
            'yandex_id'    => $yandexId,
            'name'         => $orgInfo['name'] ?? $organization->name ?? 'Неизвестная организация',
            'rating'       => (float)($orgInfo['rating'] ?? $organization->rating),
            'rating_count' => (int)($orgInfo['ratingCount'] ?? $organization->rating_count),
            'review_count' => (int)($orgInfo['reviewCount'] ?? $organization->review_count),
            'error_message'=> null,
        ]);

        if ($organization->isDirty()) {
            $organization->last_parsed_at = now();
            $organization->status = 'completed';
        }

        $reviewsList = $result['reviews'] ?? [];
        $now = now();

        $reviewsData = collect($reviewsList)->map(fn ($review) => [
            'organization_id'  => $organization->id,
            'author_name'      => $review['authorName'] ?? 'Аноним',
            'author_avatar'    => $review['authorAvatar'] ?? null,
            'rating'           => (int)($review['rating'] ?? 5),
            'text'             => $review['text'] ?? '',
            'published_at_str' => $review['publishedAtStr'] ?? '',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::transaction(function () use ($organization, $reviewsData) {
            if ($organization->isDirty()) {
                $organization->save();
            }

            $organization->reviews()->delete();

            if ($reviewsData->isNotEmpty()) {
                $reviewsData->lazy()
                    ->chunk(100)
                    ->each(fn ($chunk) => Review::insert($chunk->toArray()));
            }
        });
    }

    /**
     * Extract yandex ID from URL
     */
    private function extractYandexId($url): ?string
    {
        if (preg_match('/\/org\/(?:[^\/]+\/)?(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/oid=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
