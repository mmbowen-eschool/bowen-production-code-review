<?php

namespace App\Models\DanceStudio;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'student_id',
        'package_type_id',
        'payment_id',
        'purchased_at',
        'expires_at',
        'total_units',
        'remaining_units',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function packageType(): BelongsTo
    {
        return $this->belongsTo(PackageType::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function packageTransactions(): HasMany
    {
        return $this->hasMany(PackageTransaction::class);
    }
}

