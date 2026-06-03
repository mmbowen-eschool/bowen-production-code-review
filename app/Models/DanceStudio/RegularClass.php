<?php

namespace App\Models\DanceStudio;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegularClass extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'room_id',
        'teacher_id',
        'class_type_id',
        'day_of_week',
        'start_time',
        'end_time',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }
}

