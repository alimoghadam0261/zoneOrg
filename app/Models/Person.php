<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'phone',
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

    /**
     * Person-specific access rules (target_type = person, target_id = this person).
     */
    public function zoneRules(): HasMany
    {
        return $this->hasMany(ZoneAccessRule::class, 'target_id')->where('target_type', 'person');
    }

    /**
     * Zones this person is explicitly linked to through an access rule.
     * (Wildcard / department / contract rules are not per-person links.)
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'zone_access_rules', 'target_id', 'zone_id')
            ->where('zone_access_rules.target_type', 'person')
            ->withPivot(['access_type'])
            ->withTimestamps();
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
                ->orWhere('phone', 'like', $like)
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
