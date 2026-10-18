<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['details' => 'array'];

    public const LABELS = [
        'login' => 'Signed in',
        'admin_login' => 'Admin / staff signed in',
        'login_failed' => 'Wrong password',
        'lockout' => 'Too many attempts — blocked',
        'logout' => 'Signed out',
        'idle_logout' => 'Signed out after inactivity',
        'two_factor_sent' => 'Sign-in code sent',
        'two_factor_failed' => 'Wrong sign-in code',
        'two_factor_passed' => 'Sign-in code accepted',
        'new_device' => 'Admin sign-in from a new device',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
