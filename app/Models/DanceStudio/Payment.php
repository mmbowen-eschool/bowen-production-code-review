<?php

namespace App\Models\DanceStudio;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'student_id',
        'amount',
        'currency',
        'method',
        'status',
        'paid_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentPackages(): HasMany
    {
        return $this->hasMany(StudentPackage::class);
    }

    public function packageTransactions(): HasMany
    {
        return $this->hasMany(PackageTransaction::class);
    }
}

