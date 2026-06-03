<?php

namespace App\Models\DanceStudio;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrivateBooking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'student_id',
        'teacher_id',
        'room_id',
        'class_type_id',
        'start_at',
        'end_at',
        'status',
        'requested_by_user_id',
        'confirmed_by_user_id',
        'cancelled_by_user_id',
        'cancelled_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'requested_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'confirmed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'cancelled_by_user_id');
    }

    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class);
    }
}
