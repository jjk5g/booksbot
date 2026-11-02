<?php
// Server-side configuration. Database credentials are managed by Hostinger
// and exposed to PHP as environment variables. The private notification
// address is stored outside public_html.
$ownerFile = '/home/u751177156/.costa_owner';
$ownerEmail = is_file($ownerFile) ? trim((string) @file_get_contents($ownerFile)) : '';
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'u751177156_costalive',
        'user' => getenv('DB_USER') ?: 'u751177156_costalive',
        'pass' => getenv('DB_PASSWORD') ?: '',
    ],
    'owner_email' => $ownerEmail,
    'site_url' => 'https://costacams.com',
    'mail_from' => 'noreply@costacams.com',
    'timezone' => 'Australia/Brisbane',
    // Evaluation endpoint by default. Configure a commercial key before monetising.
    'open_meteo_key' => '',
    'open_meteo_commercial' => false,
];
