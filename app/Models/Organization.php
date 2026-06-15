<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Get the reviews for the organization.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
