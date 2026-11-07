<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new secure API token for the user.
     *
     * @return array{plainTextToken: string, token: self}
     */
    public static function createTokenForUser(User $user, string $name = 'mobile_app', int $expiresInDays = 60, array $abilities = ['*']): array
    {
        $plainText = Str::random(40);
        $hashedToken = hash('sha256', $plainText);

        $tokenRecord = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return [
            'plainTextToken' => $plainText,
            'token' => $tokenRecord,
        ];
    }

    /**
     * Find a token record by its plain text token.
     */
    public static function findByPlainTextToken(string $plainTextToken): ?self
    {
        $hashedToken = hash('sha256', $plainTextToken);

        return self::where('token', $hashedToken)
            ->with('user')
            ->first();
    }

    /**
     * Check if the token is currently active and not expired.
     */
    public function isValid(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}

