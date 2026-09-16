<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tier 2 / T2-B — a tenant's public-API credential.
 *
 * The secret is shown once at creation and stored only as a hash. Auth is
 * `Authorization: Bearer <key_id>.<secret>` (see the 'apikey' guard registered
 * in AppServiceProvider). Implements Authenticatable so `auth:apikey` treats it
 * as the request principal.
 */
class ApiClient extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['secret_hash'];

    /**
     * @return array{model: self, secret: string}  the plaintext secret is returned ONLY here
     */
    public static function issue(int $tenantId, string $name, array $scopes, ?int $createdBy = null, int $rateLimit = 120): array
    {
        $keyId = 'ak_' . Str::lower(Str::random(16));
        $secret = Str::random(40);

        $model = static::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_id' => $keyId,
            'secret_hash' => hash('sha256', $secret),
            'scopes' => array_values($scopes),
            'rate_limit_per_min' => $rateLimit,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);

        return ['model' => $model, 'secret' => $keyId . '.' . $secret];
    }

    public function secretMatches(string $secret): bool
    {
        return hash_equals($this->secret_hash, hash('sha256', $secret));
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    public function isUsable(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
