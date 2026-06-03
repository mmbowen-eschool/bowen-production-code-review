<?php

namespace App\Models\DanceStudio;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'student_id',
        'student_package_id',
        'type',
        'units_delta',
        'balance_after',
        'occurred_at',
        'attendance_record_id',
        'payment_id',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentPackage(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by_user_id');
    }
}
