<?php

$users = [
    'version' => '1.0.0',
    'private' => true,
    'users' => [
        [
            'id' => '1',
            'username' => 'administrator',
            'email' => 'administrator@intilab.com',
            'name' => 'Administrator',
            'role' => 'admin',
            'passwordHash' => password_hash('Administrator123', PASSWORD_BCRYPT),
        ],
        [
            'id' => '2',
            'username' => 'dedi',
            'email' => 'dedi@intilab.com',
            'name' => 'Dedi',
            'role' => 'admin',
            'passwordHash' => password_hash('Skyhwk12', PASSWORD_BCRYPT),
        ],
    ],
];

$path = dirname(__DIR__) . '/storage/app/data/controlAccess/users.json';
file_put_contents($path, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

foreach ($users['users'] as $user) {
    $password = $user['username'] === 'dedi' ? 'Skyhwk12' : 'Administrator123';
    $ok = password_verify($password, $user['passwordHash']);
    echo $user['username'] . ': ' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
}
