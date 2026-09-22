<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial number sequences
    |--------------------------------------------------------------------------
    |
    | SF and PF are two completely independent sequences. These values are the
    | numbers the FIRST record of each kind receives. They are only read once,
    | by the create_number_sequences_table migration, which stores them in the
    | number_sequences table. From then on the database row is the single
    | source of truth; changing these values later has no effect.
    |
    */

    'sequences' => [
        'SF' => (int) env('SF_START_NUMBER', 26090119),
        'PF' => (int) env('PF_START_NUMBER', 26090138),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder password
    |--------------------------------------------------------------------------
    |
    | Initial password for the users created by `php artisan db:seed`.
    | Leave empty (recommended) and the seeder generates a random password
    | for every new user and prints it once. Passwords are always hashed
    | (Argon2id) before they are stored; nothing is kept in plaintext.
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
