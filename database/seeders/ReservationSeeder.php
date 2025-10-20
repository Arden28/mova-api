<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Models\Bus;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        // Pull a pool of existing bus IDs (ensure BusSeeder ran first)
        $busIds = Bus::query()->pluck('id')->all();
        if (empty($busIds)) {
            $this->command->warn('No buses found. Run BusSeeder first.');
            return;
        }

        $now = Carbon::now();

        // Helper to generate a unique-ish public code like BZV-000123
        $makeCode = function (string $prefix = 'BZV'): string {
            return $prefix.'-'.str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        };

        // Small catalog of events
        $events = [
            'school_trip', 'university_trip', 'educational_tour', 'student_transport',
            'wedding', 'funeral', 'birthday', 'baptism', 'family_meeting',
            'conference', 'seminar', 'company_trip', 'business_mission', 'staff_shuttle',
            'football_match', 'sports_tournament', 'concert', 'festival', 'school_competition',
            'tourist_trip', 'group_excursion', 'pilgrimage', 'site_visit', 'airport_transfer',
            'election_campaign', 'administrative_mission', 'official_trip',
            'private_transport', 'special_event', 'simple_rental',
        ];

        // Some realistic waypoints & routes around CG (labels only)
        $routes = [
            [
                'from' => 'Brazzaville, Marché Total',
                'to'   => 'Aéroport Maya-Maya',
                'waypoints' => [
                    ['lat' => -4.265, 'lng' => 15.248, 'label' => 'Marché Total'],
                    ['lat' => -4.252, 'lng' => 15.249, 'label' => 'Avenue de la Paix'],
                    ['lat' => -4.251, 'lng' => 15.253, 'label' => 'Aéroport Maya-Maya'],
                ],
                'km' => 12.6,
            ],
            [
                'from' => 'Brazzaville, Poto-Poto',
                'to'   => 'Kintélé',
                'waypoints' => [
                    ['lat' => -4.269, 'lng' => 15.251, 'label' => 'Poto-Poto'],
                    ['lat' => -4.240, 'lng' => 15.290, 'label' => 'Corniche'],
                    ['lat' => -4.100, 'lng' => 15.250, 'label' => 'Kintélé'],
                ],
                'km' => 32.4,
            ],
            [
                'from' => 'Brazzaville, Bacongo',
                'to'   => 'Makélékélé',
                'waypoints' => [
                    ['lat' => -4.300, 'lng' => 15.260, 'label' => 'Bacongo'],
                    ['lat' => -4.290, 'lng' => 15.265, 'label' => 'Pont du Djoué'],
                    ['lat' => -4.280, 'lng' => 15.270, 'label' => 'Makélékélé'],
                ],
                'km' => 15.2,
            ],
            [
                'from' => 'Brazzaville, Ouenze',
                'to'   => 'Talangaï',
                'waypoints' => [
                    ['lat' => -4.240, 'lng' => 15.280, 'label' => 'Ouenze'],
                    ['lat' => -4.230, 'lng' => 15.285, 'label' => 'Avenue des 3 Martyrs'],
                    ['lat' => -4.220, 'lng' => 15.290, 'label' => 'Talangaï'],
                ],
                'km' => 10.8,
            ],
            [
                'from' => 'Brazzaville, Plateau',
                'to'   => 'Mfilou',
                'waypoints' => [
                    ['lat' => -4.262, 'lng' => 15.285, 'label' => 'Plateau'],
                    ['lat' => -4.270, 'lng' => 15.270, 'label' => 'Avenue de l’OUA'],
                    ['lat' => -4.280, 'lng' => 15.255, 'label' => 'Mfilou'],
                ],
                'km' => 18.9,
            ],
        ];

        // Sample passengers (mix)
        $passengers = [
            ['name' => 'Jean K.',    'phone' => '+242060001111', 'email' => 'jean.k@example.com'],
            ['name' => 'Mireille O.', 'phone' => '+242060001112', 'email' => 'mireille.o@example.com'],
            ['name' => 'Gildas M.',  'phone' => '+242060001113', 'email' => 'gildas.m@example.com'],
            ['name' => 'Prisca M.',  'phone' => '+242060001114', 'email' => 'prisca.m@example.com'],
            ['name' => 'Nadia N.',   'phone' => '+242060001115', 'email' => 'nadia.n@example.com'],
            ['name' => 'Romaric K.', 'phone' => '+242060001116', 'email' => 'romaric.k@example.com'],
        ];

        // Statuses
        $statuses = ['pending', 'confirmed', 'cancelled', 'processing'];

        // Build 20 reservations (mix of past/future, statuses, events)
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $route = $routes[array_rand($routes)];
            $pax   = $passengers[array_rand($passengers)];
            $status = $statuses[array_rand($statuses)];
            $event  = $events[array_rand($events)];

            // Spread trip dates across -10 days to +20 days
            $tripDate = (clone $now)->addDays(random_int(-10, 20))->setTime(random_int(6, 20), [0, 15, 30, 45][array_rand([0,1,2,3])]);

            $rows[] = [
                'id'               => (string) Str::uuid(),
                'code'             => $makeCode('BZV'),
                'trip_date'        => $tripDate,
                'from_location'    => $route['from'],
                'to_location'      => $route['to'],
                'passenger_name'   => $pax['name'],
                'passenger_phone'  => $pax['phone'],
                'passenger_email'  => $pax['email'],
                'event'            => $event,
                'seats'            => random_int(1, 6),
                'price_total'      => random_int(10, 60) * 1000, // e.g., 10k – 60k XAF
                'status'           => $status,
                'waypoints'        => json_encode($route['waypoints']),
                'distance_km'      => $route['km'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        // Insert reservations
        DB::table('reservations')->insert($rows);

        // Attach 1–3 buses per reservation in pivot
        $pivotRows = [];
        foreach ($rows as $r) {
            $count = random_int(1, 3);
            // pick distinct buses
            $picked = collect($busIds)->shuffle()->take($count)->values()->all();
            foreach ($picked as $busId) {
                $pivotRows[] = [
                    'reservation_id' => $r['id'],
                    'bus_id'         => $busId,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        DB::table('reservation_buses')->insert($pivotRows);

        $this->command->info('Reservations seeded successfully (with pivot reservation_buses).');
    }
}
