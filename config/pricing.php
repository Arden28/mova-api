<?php

return [
    // Currency label only (no formatting)
    'currency' => 'XAF', // FCFA

    // Vehicle types with base rules (from doc)
    // Hiace: 425 / km, +40% motivation
    // Coaster: 725 / km, +25% motivation  :contentReference[oaicite:0]{index=0}
    'vehicles' => [
        'hiace' => [
            'per_km' => 425,
            'motivation_percent' => 0.40, // +40%  :contentReference[oaicite:1]{index=1}
        ],
        'coaster' => [
            'per_km' => 725,
            'motivation_percent' => 0.25, // +25%  :contentReference[oaicite:2]{index=2}
        ],
    ],

    // Event multipliers (25–30% depending on event)  :contentReference[oaicite:3]{index=3}
    'events' => [
        'none'    => 0.00,
        'wedding'=> 0.30, // Mariage +30%  :contentReference[oaicite:4]{index=4}
        'funeral' => 0.25, // Obsèques +25%  :contentReference[oaicite:5]{index=5}
        'church'  => 0.20, // Églises +20%   :contentReference[oaicite:6]{index=6}
    ],

    // Client Mobile Money fees (+4% on the client price)  :contentReference[oaicite:7]{index=7}
    'mobile_money_client_percent' => 0.04,

    // Commission Móva Mobility (13% deducted from the rounded client amount)  :contentReference[oaicite:8]{index=8}
    'commission_percent' => 0.13,

    // Mobile Money applied to bus payout (+3.5%)  :contentReference[oaicite:9]{index=9}
    'mobile_money_bus_percent' => 0.035,

    // Special upward rounding to local payable amounts (applies to client and bus)
    // Rules from your doc:
    // 1–24 => 25, 26–49 => 50, 51–74 => 75, >75 => next hundred  :contentReference[oaicite:10]{index=10}
    'rounding' => [
        'mode' => 'step25_up', // custom strategy
    ],
];
