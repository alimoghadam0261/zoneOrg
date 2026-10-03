<?php

namespace App\Models;

use App\Services\Geofencing\GeoJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const TYPES = ['polygon', 'circle', 'rectangle'];

    protected $fillable = [
        'name',
        'code',
        'type',
        'geometry',
        'min_lat',
        'max_lat',
        'min_lng',
        'max_lng',
        'center_lat',
        'center_lng',
        'radius',
        'color',
        'severity_level',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'is_active' => 'boolean',
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lng' => 'float',
            'max_lng' => 'float',
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius' => 'integer',
        ];
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(ZoneAccessRule::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ZoneEventLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Recompute the bounding box (and circle centre) from the GeoJSON geometry.
     * The bounding box is what Phase-1 of the geofencing engine filters on.
     */
    public function syncBoundingBox(): void
    {
        $geometry = GeoJson::normalize($this->geometry, $this->type);
        $this->geometry = $geometry;

        $bbox = GeoJson::bbox($geometry);

        if ($bbox !== null) {
            $this->min_lat = $bbox[0];
            $this->max_lat = $bbox[2];
            $this->min_lng = $bbox[1];
            $this->max_lng = $bbox[3];
        }

        if ($this->type === 'circle') {
            $center = GeoJson::center($geometry);
            $this->center_lat = $center['lat'];
            $this->center_lng = $center['lng'];
            $this->radius = (int) ($geometry['properties']['radius'] ?? $this->radius);
        } else {
            $this->center_lat = null;
            $this->center_lng = null;
            $this->radius = null;
        }
    }

    /**
     * Flat GeoJSON ring [[lat, lng], ...] used by the ray-casting test.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function ring(): array
    {
        return GeoJson::ring($this->geometry);
    }

    public function isCircle(): bool
    {
        return $this->type === 'circle';
    }

    public function isForbidden(): bool
    {
        return $this->accessRules()
            ->where('access_type', 'deny')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function toMapArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'color' => $this->color,
            'severity_level' => $this->severity_level,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'geometry' => $this->geometry,
            'bbox' => [$this->min_lat, $this->min_lng, $this->max_lat, $this->max_lng],
        ];
    }
}
