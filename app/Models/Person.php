<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasFactory;

    public const CONTRACT_TYPES = ['employee', 'contractor', 'visitor'];

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'personnel_code',
        'full_name',
        'national_id',
        'department',
        'contract_type',
        'avatar_url',
        'status',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function pings(): HasMany
    {
        return $this->hasMany(LocationPing::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ZoneEventLog::class);
    }

    public function device(): HasOne
    {
        return $this->hasOne(Device::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('full_name', 'like', $like)
                ->orWhere('personnel_code', 'like', $like)
                ->orWhere('department', 'like', $like);
        });
    }

    public function contractTypeLabel(): string
    {
        return match ($this->contract_type) {
            'contractor' => 'پیمانکار',
            'visitor' => 'مهمان',
            default => 'کارمند',
        };
    }
}
