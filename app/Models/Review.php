<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $author_name
 * @property string|null $author_avatar
 * @property int $rating
 * @property string $text
 * @property string $published_at_str
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 *
 * @OA\Schema(
 *     schema="Review",
 *     title="Review Model",
 *     description="Yandex Maps Review detailed record",
 *     required={"organization_id", "author_name", "rating", "text", "published_at_str"},
 *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *     @OA\Property(property="organization_id", type="integer", example=1),
 *     @OA\Property(property="author_name", type="string", example="John Doe"),
 *     @OA\Property(property="author_avatar", type="string", nullable=true, example="https://example.com/avatar.jpg"),
 *     @OA\Property(property="rating", type="integer", example=5),
 *     @OA\Property(property="text", type="string", example="Great service!"),
 *     @OA\Property(property="published_at_str", type="string", example="2 дня назад"),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true, nullable=true, example="2026-06-16T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, nullable=true, example="2026-06-16T12:00:00Z")
 * )
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'author_name',
        'author_avatar',
        'rating',
        'text',
        'published_at_str',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Get the organization that owns the review.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
