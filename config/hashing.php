<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Passwords in this application are hashed with Argon2id. Do not change
    | this after users exist unless you also plan to migrate their hashes:
    | with "verify" enabled (below) a hash created by another algorithm is
    | rejected instead of silently accepted.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    | Argon2 cost parameters. The defaults below are PHP's own defaults
    | (64 MiB, 4 iterations, 1 thread).
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    | Transparently re-hash a password on login when the cost parameters
    | above have been raised since it was stored.
    */

    'rehash_on_login' => true,

];
