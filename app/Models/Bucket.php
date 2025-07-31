<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class Bucket extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'bucket_name',
        'region',
        'access_key_id',
        'secret_access_key',
        'endpoint',
        'public_url',
        'is_active',
        'last_connected_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    /**
     * Encrypt access_key_id before storing
     */
    protected function accessKeyId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Encrypt secret_access_key before storing
     */
    protected function secretAccessKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Get the user that owns the bucket
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only active buckets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get S3/R2 client configuration array
     */
    public function getClientConfig(): array
    {
        $config = [
            'credentials' => [
                'key' => $this->access_key_id,
                'secret' => $this->secret_access_key,
            ],
            'version' => 'latest',
        ];

        if ($this->provider === 'r2') {
            $config['endpoint'] = $this->endpoint;
            $config['region'] = 'auto';
            $config['use_path_style_endpoint'] = true;
        } else {
            $config['region'] = $this->region ?: 'us-east-1';
        }

        return $config;
    }
}
