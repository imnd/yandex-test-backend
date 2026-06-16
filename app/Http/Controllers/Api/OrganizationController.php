<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSettingsRequest;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Organization",
 *     description="Endpoints to manage Yandex Maps Organization and reviews"
 * )
 */
class OrganizationController extends Controller
{
    protected OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    /**
     * @OA\Get(
     *     path="/api/organization",
     *     summary="Get the current active organization",
     *     tags={"Organization"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="organization", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="yandex_id", type="string", example="1124715036"),
     *                 @OA\Property(property="url", type="string", example="https://yandex.ru/maps/org/1124715036"),
     *                 @OA\Property(property="name", type="string", example="Yandex"),
     *                 @OA\Property(property="rating", type="number", format="float", example=4.5),
     *                 @OA\Property(property="rating_count", type="integer", example=120),
     *                 @OA\Property(property="review_count", type="integer", example=100),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="error_message", type="string", nullable=true, example=null),
     *                 @OA\Property(property="last_parsed_at", type="string", format="date-time", example="2026-06-16T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getOrganization(): JsonResponse
    {
        return response()->json([
            'organization' => $this->organizationService->getActiveOrganization(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/organization/settings",
     *     summary="Save Yandex Maps URL and start reviews import",
     *     tags={"Organization"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", example="https://yandex.ru/maps/org/1124715036")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL saved and parsing started",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization URL saved. Reviews import started."),
     *             @OA\Property(property="organization", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function saveSettings(SaveSettingsRequest $request): JsonResponse
    {
        $url = $request->input('url');

        return response()->json([
            'message' => 'Organization URL saved. Reviews import started.',
            'organization' => $this->organizationService->saveSettings($url),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/organization/reviews",
     *     summary="Get reviews for the active organization (paginated)",
     *     tags={"Organization"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated reviews",
     *         @OA\JsonContent(
     *             @OA\Property(property="reviews", type="array",
     *                 @OA\Items(ref="#/components/schemas/Review")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=50),
     *                 @OA\Property(property="total", type="integer", example=250)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getReviews(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 50), 100);
        $paginator = $this->organizationService->getReviews($perPage);

        return response()->json([
            'reviews' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/organization/refresh",
     *     summary="Manually trigger re-parsing of the current organization reviews",
     *     tags={"Organization"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Refresh started",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reviews refresh started."),
     *             @OA\Property(property="organization", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not configured"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function refreshReviews(): JsonResponse
    {
        return response()->json([
            'message' => 'Reviews refresh started.',
            'organization' => $this->organizationService->refreshReviews(),
        ]);
    }
}
