<?php

namespace App\Models\DanceStudio;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassType extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'name',
        'kind',
        'default_duration_minutes',
        'default_deduct_units',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function regularClasses(): HasMany
    {
        return $this->hasMany(RegularClass::class);
    }

    public function privateBookings(): HasMany
    {
        return $this->hasMany(PrivateBooking::class);
    }
}

