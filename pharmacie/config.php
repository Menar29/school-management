<?php
// Configuration par défaut. Ne modifiez pas ce fichier :
// copiez config.local.example.php en config.local.php et changez-y vos valeurs.
$config = [
    'app_name'  => 'Gestion Pharmacie',
    'pharmacie' => '[Nom de la pharmacie]',
    'timezone'  => 'Africa/Niamey',
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'pharmacie',
        'user'     => 'pharma',
        'password' => 'pharma',
    ],
    // Mettre à true en production derrière HTTPS (cookies « secure »)
    'https' => false,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    $config = array_replace_recursive($config, $local);
}
return $config;
