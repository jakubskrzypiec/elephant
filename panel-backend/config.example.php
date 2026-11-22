<?php
// Copy OUTSIDE the web root, then set ELEPHANT_CONFIG to its absolute path.
return [
    'environment' => 'production',
    'base_path' => '', // '/test' for a staging subdirectory, otherwise ''.
    'site_url' => 'https://elephant.com.pl',
    'private_dir' => '/absolute/private/elephant',
    'dsn' => 'mysql:host=localhost;dbname=elephant;charset=utf8mb4',
    'db_user' => '',
    'db_password' => '',
    'secret' => '', // Generate with bin2hex(random_bytes(32)); never reuse an example value.
    'mail_transport' => 'smtp',
    'smtp' => [
        'host' => '', 'port' => 587, 'encryption' => 'tls',
        'username' => '', 'password' => '',
        'from' => '', 'from_name' => 'ELEPHANT Interior Design',
    ],
];
