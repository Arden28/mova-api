<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // ------------------ Company info ------------------
            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->string('support_email');
            $table->string('support_phone');
            $table->string('country', 2)->default('CG');
            $table->string('timezone')->default('Africa/Brazzaville');
            $table->string('currency', 3)->default('XAF');
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();

            // ------------------ Notifications ------------------
            $table->boolean('email_enabled')->default(true);
            $table->string('email_provider')->nullable();
            $table->string('email_from')->nullable();
            $table->string('email_api_key')->nullable();
            $table->boolean('sms_enabled')->default(true);
            $table->string('sms_provider')->nullable();
            $table->string('sms_sender_id')->nullable();
            $table->string('sms_api_key')->nullable();
            $table->boolean('notify_on_booking')->default(true);
            $table->boolean('notify_on_payment')->default(true);
            $table->boolean('notify_on_cancellation')->default(true);

            // ------------------ Payments ------------------
            $table->string('gateway')->default('cash');
            $table->enum('mode', ['test', 'live'])->default('test');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('allow_partial')->default(true);
            $table->decimal('deposit_percent', 5, 2)->default(20);
            $table->integer('refund_window_days')->default(7);
            $table->string('public_key')->nullable();
            $table->string('secret_key')->nullable();

            // ------------------ Booking ------------------
            $table->integer('seat_hold_mins')->default(15);
            $table->integer('auto_cancel_unpaid_mins')->default(30);
            $table->boolean('allow_waitlist')->default(true);
            $table->boolean('dynamic_pricing')->default(true);
            $table->decimal('base_fare_per_km', 10, 2)->default(12);
            $table->decimal('min_fare', 10, 2)->default(150);
            $table->decimal('peak_multiplier', 5, 2)->default(1.3);
            $table->decimal('weekend_multiplier', 5, 2)->default(1.15);
            $table->decimal('cancellation_fee_percent', 5, 2)->default(10);

            // ------------------ Integrations ------------------
            $table->string('mapbox_token')->nullable();
            $table->string('sentry_dsn')->nullable();
            $table->string('analytics_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
