<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Organization
 *
 * @property int $id
 * @property string $yandex_id
 * @property string $url
 * @property string|null $name
 * @property float|null $rating
 * @property int|null $rating_count
 * @property int|null $review_count
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $last_parsed_at
  * @property Carbon|null $created_at
  * @property Carbon|null $updated_at
  *
  * @property-read Collection|Review[] $reviews
  *
  * @OA\Schema(
  *     schema="Organization",
  *     title="Organization Model",
  *     description="Yandex Maps Organization details",
  *     required={"yandex_id", "url"},
  *     @OA\Property(property="id", type="integer", readOnly=true, example=1),
  *     @OA\Property(property="yandex_id", type="string", example="1124715036"),
  *     @OA\Property(property="url", type="string", example="https://yandex.ru/maps/org/1124715036"),
  *     @OA\Property(property="name", type="string", nullable=true, example="Yandex"),
  *     @OA\Property(property="rating", type="number", format="float", nullable=true, example=4.5),
  *     @OA\Property(property="rating_count", type="integer", nullable=true, example=120),
  *     @OA\Property(property="review_count", type="integer", nullable=true, example=100),
  *     @OA\Property(property="status", type="string", example="completed"),
  *     @OA\Property(property="error_message", type="string", nullable=true, example=null),
  *     @OA\Property(property="last_parsed_at", type="string", format="date-time", nullable=true, example="2026-06-16T12:00:00Z"),
  *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true, nullable=true, example="2026-06-16T12:00:00Z"),
  *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, nullable=true, example="2026-06-16T12:00:00Z")
  * )
  */
class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_id',
        'url',
        'name',
        'rating',
        'rating_count',
        'review_count',
        'status',
        'error_message',
        'last_parsed_at',
    ];

    protected $casts = [
        'rating' => 'float',
        'rating_count' => 'integer',
        'review_count' => 'integer',
        'last_parsed_at' => 'datetime',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
