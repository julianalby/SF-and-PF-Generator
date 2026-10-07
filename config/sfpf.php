<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Initial number sequence suffixes
    |--------------------------------------------------------------------------
    |
    | SF and PF are two completely independent sequences. These values are the
    | three-digit suffixes the FIRST record of each kind receives. They are only
    | read by the create_number_sequences_table migration. From then on the
    | database row is the single source of truth; changing these values later
    | has no effect.
    |
    | The generated number itself is YYMM + this three-digit suffix.
    |
    */
    'sequences' => [
        'SF' => (int) env('SF_START_NUMBER', 119),
        'PF' => (int) env('PF_START_NUMBER', 138),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder password
    |--------------------------------------------------------------------------
    |
    | Initial password for the users created by `php artisan db:seed`.
    | Leave empty (recommended) and the seeder generates a random password
    | for every new user and prints it once. Passwords are always hashed
    | (Argon2id) before being stored; nothing is kept in plaintext.
    |
    */
    'seed_password' => env('SEED_USER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    */

    // Rows per page on the admin SF / PF database pages.
    'per_page' => (int) env('SFPF_PER_PAGE', 50),

    // How "Create Date" is shown (in APP_TIMEZONE).
    'date_format' => 'Y-m-d H:i:s',
];
