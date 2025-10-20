<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ReservationBus extends Pivot
{
    protected $table = 'reservation_buses';
    public $incrementing = false;

    protected $fillable = [
        'reservation_id',
        'bus_id',
        // 'allocated_seats', 'notes', ...
    ];
}

