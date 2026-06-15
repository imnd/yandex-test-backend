<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
