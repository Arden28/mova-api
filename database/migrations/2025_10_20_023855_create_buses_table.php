<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();

            $table->string('plate')->unique()->comment('Vehicle plate number');
            $table->integer('capacity')->default(0)->comment('Seating capacity');
            $table->string('name')->nullable()->comment('Optional name for the bus');
            $table->enum('type', ['standard', 'luxury', 'minibus', 'hiace', 'coaster'])->nullable()->comment('Bus type');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->comment('Current bus status');
            $table->string('model')->nullable()->comment('Bus model');
            $table->integer('year')->nullable()->comment('Year of manufacture');
            $table->integer('mileage_km')->nullable()->comment('Total mileage in kilometers');
            $table->date('last_service_date')->nullable()->comment('Last service date');

            // Insurance
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->date('insurance_valid_until')->nullable();

            // Ownership & assignment (normal integer FKs to users.id)
            $table->foreignId('operator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Owner user FK');

            $table->foreignId('assigned_driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Assigned driver user FK');

            $table->timestamps();

            // Indexes for common queries
            $table->index('operator_id');
            $table->index('assigned_driver_id');
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
