<?php

namespace App\Models\DanceStudio;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'private_booking_id',
        'student_id',
        'teacher_id',
        'room_id',
        'status',
        'checked_in_at',
        'checked_in_by_user_id',
        'voided_at',
        'voided_by_user_id',
        'notes',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function privateBooking(): BelongsTo
    {
        return $this->belongsTo(PrivateBooking::class);
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

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'checked_in_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'voided_by_user_id');
    }

    public function packageTransactions(): HasMany
    {
        return $this->hasMany(PackageTransaction::class);
    }
}
