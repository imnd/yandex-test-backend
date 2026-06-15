<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSettingsRequest;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Get the current active organization.
     */
    public function getOrganization(): JsonResponse
    {
        $organization = Organization::first();

        return response()->json([
            'organization' => $organization,
        ]);
    }

    /**
     * Save the Yandex Maps organization URL and trigger parsing.
     */
    public function saveSettings(SaveSettingsRequest $request): JsonResponse
    {
        $url = $request->input('url');
        
        // Extract temporary or parsed yandex ID from URL
        $yandexId = 'pending_' . uniqid();
        if (preg_match('/\/org\/(?:[^\/]+\/)?(\d+)/', $url, $matches)) {
            $yandexId = $matches[1];
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

        return response()->json([
            'message' => 'Organization URL saved. Reviews import started.',
            'organization' => $organization,
        ]);
    }

    /**
     * Get reviews for the active organization (paginated, 50 per page).
     */
    public function getReviews(Request $request): JsonResponse
    {
        $organization = Organization::first();

        if (!$organization) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        // Paginate by 50 reviews, ordered by ID desc
        $reviews = $organization->reviews()
            ->orderBy('id', 'desc')
            ->paginate(50);

        return response()->json($reviews);
    }

    /**
     * Manually trigger re-parsing of the current organization reviews.
     */
    public function refreshReviews(): JsonResponse
    {
        $organization = Organization::first();

        if (!$organization) {
            return response()->json([
                'message' => 'No organization configured.',
            ], 404);
        }

        $organization->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ParseOrganizationJob::dispatch($organization);

        return response()->json([
            'message' => 'Reviews refresh started.',
            'organization' => $organization,
        ]);
    }
}
