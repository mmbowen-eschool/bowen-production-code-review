<?php

namespace App\Models\DanceStudio;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'timezone',
        'currency',
        'phone',
        'address',
        'is_active',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(AppUser::class, 'studio_id');
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function classTypes(): HasMany
    {
        return $this->hasMany(ClassType::class);
    }

    public function packageTypes(): HasMany
    {
        return $this->hasMany(PackageType::class);
    }

    public function studentPackages(): HasMany
    {
        return $this->hasMany(StudentPackage::class);
    }

    public function regularClasses(): HasMany
    {
        return $this->hasMany(RegularClass::class);
    }

    public function privateBookings(): HasMany
    {
        return $this->hasMany(PrivateBooking::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function packageTransactions(): HasMany
    {
        return $this->hasMany(PackageTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
