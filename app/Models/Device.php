<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use HasApiTokens, HasFactory;

    public const TYPES = ['mobile_app', 'gps_tag'];

    protected $fillable = [
        'device_uid',
        'type',
        'person_id',
        'battery_level',
        'last_seen_at',
        'api_token',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'battery_level' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function pings(): HasMany
    {
        return $this->hasMany(LocationPing::class);
    }

    /**
     * Mint a Sanctum personal-access token and mirror its sha256 digest into
     * `devices.api_token` so authenticating a ping costs one indexed lookup.
     */
    public function issueToken(): string
    {
        $token = $this->createToken('zone-telemetry');

        $this->forceFill([
            'api_token' => hash('sha256', $token->plainTextToken),
        ])->save();

        return $token->plainTextToken;
    }

    public function revokeToken(): void
    {
        $this->tokens()->delete();
        $this->forceFill(['api_token' => null])->save();
    }

    public static function findByToken(?string $plainTextToken): ?self
    {
        if (! $plainTextToken) {
            return null;
        }

        $digest = hash('sha256', $plainTextToken);

        $device = static::query()->where('api_token', $digest)->first();

        if ($device) {
            return $device;
        }

        // Fallback for tokens minted through Sanctum only.
        $tokenable = \Laravel\Sanctum\PersonalAccessToken::findToken($plainTextToken)?->tokenable;

        return $tokenable instanceof self ? $tokenable : null;
    }

    public function touchPing(int $battery): void
    {
        $this->forceFill([
            'last_seen_at' => now(),
            'battery_level' => $battery,
        ])->saveQuietly();
    }
}
