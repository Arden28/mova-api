<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    // protected $table = 'company_settings';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Company info
        'company_name', 'legal_name', 'support_email', 'support_phone',
        'country', 'timezone', 'currency', 'address', 'logo_path',

        // Notifications
        'email_enabled', 'email_provider', 'email_from', 'email_api_key',
        'sms_enabled', 'sms_provider', 'sms_sender_id', 'sms_api_key',
        'notify_on_booking', 'notify_on_payment', 'notify_on_cancellation',

        // Payments
        'gateway', 'mode', 'tax_rate', 'allow_partial', 'deposit_percent',
        'refund_window_days', 'public_key', 'secret_key',

        // Booking
        'seat_hold_mins', 'auto_cancel_unpaid_mins', 'allow_waitlist',
        'dynamic_pricing', 'base_fare_per_km', 'min_fare', 'peak_multiplier',
        'weekend_multiplier', 'cancellation_fee_percent',

        // Integrations
        'mapbox_token', 'sentry_dsn', 'analytics_id', 'webhook_url', 'webhook_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        // Notifications
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'notify_on_booking' => 'boolean',
        'notify_on_payment' => 'boolean',
        'notify_on_cancellation' => 'boolean',

        // Payments
        'allow_partial' => 'boolean',
        'tax_rate' => 'decimal:2',
        'deposit_percent' => 'decimal:2',

        // Booking
        'seat_hold_mins' => 'integer',
        'auto_cancel_unpaid_mins' => 'integer',
        'allow_waitlist' => 'boolean',
        'dynamic_pricing' => 'boolean',
        'base_fare_per_km' => 'decimal:2',
        'min_fare' => 'decimal:2',
        'peak_multiplier' => 'decimal:2',
        'weekend_multiplier' => 'decimal:2',
        'cancellation_fee_percent' => 'decimal:2',
    ];

    /**
     * Scope to fetch the single active setting row (if you use one).
     */
    public function scopeCurrent($query)
    {
        return $query->latest()->first();
    }

    /**
     * Helper to get formatted currency symbol.
     */
    public function getCurrencySymbolAttribute(): string
    {
        return match ($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'XAF' => 'FCFA',
            default => $this->currency,
        };
    }
}
