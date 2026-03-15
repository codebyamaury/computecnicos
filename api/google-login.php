<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? 'TU_CLIENT_ID'); // Reemplaza por tu Client ID o usa env
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? 'TU_CLIENT_SECRET'); // Reemplaza por tu Client Secret o usa env
$client->setRedirectUri(base_url() . '/api/google-callback.php');
$client->addScope('email');
$client->addScope('profile');

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit; 