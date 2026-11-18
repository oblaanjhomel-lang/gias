<?php

/*
| GIAS application settings.
*/

return [

    // Shown as the "DNSC" marker on every map.
    'campus' => [
        'name' => 'DNSC Main Campus',
        'lat' => (float) env('CAMPUS_LAT', 7.2790),
        'lng' => (float) env('CAMPUS_LNG', 125.6620),
    ],

    // Davao Region bounding box (WGS84 / SRID 4326). The single source for geocoding
    // (Nominatim viewbox), pin validation and the map's initial view.
    'region_bounds' => [
        'min_lat' => 5.5,
        'max_lat' => 8.0,
        'min_lng' => 125.0,
        'max_lng' => 126.6,
    ],

    // Date-only rules (which weeks have started, overdue) use the local calendar day.
    // Timestamps stay in the app timezone (UTC).
    'local_timezone' => env('GIAS_LOCAL_TIMEZONE', 'Asia/Manila'),

    // Deployment monitoring: an active deployment is overdue when its latest submitted or
    // signed report (or, with none, its start date) is MORE than this many days old.
    'monitoring' => [
        'overdue_days' => 14,
    ],

    // Intern evaluations (Industry Partner rates, OJT Coordinator reviews).
    'evaluation' => [
        // Ordered. Keys are stored in evaluations.ratings; labels are snapshotted at submit.
        'criteria' => [
            'quality' => 'Quality of work',
            'technical' => 'Technical competence',
            'attendance' => 'Attendance & punctuality',
            'initiative' => 'Initiative',
            'communication' => 'Communication',
            'teamwork' => 'Teamwork & conduct',
        ],
        'scale' => [
            1 => 'Poor',
            2 => 'Fair',
            3 => 'Satisfactory',
            4 => 'Very satisfactory',
            5 => 'Outstanding',
        ],
        // Descriptor for an average: the first band whose minimum it reaches.
        'descriptor_bands' => [
            ['min' => 4.50, 'label' => 'Outstanding'],
            ['min' => 3.50, 'label' => 'Very satisfactory'],
            ['min' => 2.50, 'label' => 'Satisfactory'],
            ['min' => 1.50, 'label' => 'Fair'],
            ['min' => 0, 'label' => 'Poor'],
        ],
        // final_rating = round(partner × partner_mean + coordinator × coordinator_rating, 2)
        'weights' => [
            'partner' => 0.70,
            'coordinator' => 0.30,
        ],
        'coordinator_criterion' => 'OJT requirements, weekly reports and documentation',
        // A type opens once SIGNED hours reach this percent of the program's required_ojt_hours.
        'opens_at_percent' => [
            'midterm' => 50,
            'final' => 90,
        ],
        // Completing a deployment (without an admin force) needs a REVIEWED final evaluation.
        'require_final_for_completion' => true,
    ],

    // Academic terms (month-day, inclusive), in academic-year order. The AY starts with the
    // first term: Aug 2026 – Jul 2027 is "AY 2026–2027". A deployment belongs to the term of
    // its start_date.
    'terms' => [
        1 => ['label' => '1st Semester', 'start' => '08-01', 'end' => '12-31'],
        2 => ['label' => '2nd Semester', 'start' => '01-01', 'end' => '05-31'],
        3 => ['label' => 'Midyear', 'start' => '06-01', 'end' => '07-31'],
    ],

    // Default "service radius" for the coverage figure and map circles.
    'coverage_radius_km' => 25,

    /*
    | Fuzzy logic recommender (read-only in the UI). Triangles are [a, b, c] for tri(x, a, b, c):
    | a == b is a left shoulder (1 up to b), b == c is a right shoulder (1 from b on).
    | Mamdani: rule strength = min, clip each output set, aggregate with max,
    | centroid sampled at the integers 0..100, result ÷ 100.
    */
    'fuzzy' => [
        'inputs' => [
            'fit' => [
                'low' => [0, 0, 50],
                'medium' => [25, 50, 75],
                'high' => [50, 100, 100],
            ],
            'distance_km' => [
                'near' => [0, 0, 30],
                'mid' => [15, 40, 65],
                'far' => [50, 120, 120], // ≥ 120 km is fully "far"
            ],
        ],
        // Used when the home or partner location is missing (treated as far).
        'missing_distance_km' => 120,
        'output' => [
            'poor' => [0, 0, 40],
            'fair' => [20, 45, 70],
            'good' => [50, 70, 90],
            'excellent' => [75, 100, 100],
        ],
        'rules' => [
            ['fit' => 'high', 'distance' => 'near', 'then' => 'excellent'],
            ['fit' => 'high', 'distance' => 'mid', 'then' => 'good'],
            ['fit' => 'high', 'distance' => 'far', 'then' => 'fair'],
            ['fit' => 'medium', 'distance' => 'near', 'then' => 'good'],
            ['fit' => 'medium', 'distance' => 'mid', 'then' => 'fair'],
            ['fit' => 'medium', 'distance' => 'far', 'then' => 'poor'],
            ['fit' => 'low', 'distance' => 'any', 'then' => 'poor'],
        ],
    ],

];
