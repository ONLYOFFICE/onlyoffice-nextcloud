<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (array_filter(file($envFile), fn($l) => str_contains($l, '=') && !str_starts_with(ltrim($l), '#')) as $line) {
        [$key, $value] = explode('=', trim($line), 2);
        putenv("$key=$value");
    }
}

$baseUrl = getenv('BEHAT_BASE_URL') ?: 'http://localhost';
$adminUser = getenv('NEXTCLOUD_ADMIN_USER') ?: 'admin';
$adminPassword = getenv('NEXTCLOUD_ADMIN_PASSWORD') ?: 'admin';

$profile = (new Profile('default', [
        'autoload' => [
            '' => '%paths.base%/../features/bootstrap',
        ],
    ]))->withSuite(
        (new Suite('admin'))
            ->withPaths('%paths.base%/../features/admin')
            ->addContext(AdminContext::class, [
                'baseUrl' => $baseUrl,
                'adminUser' => $adminUser,
                'adminPassword' => $adminPassword,
            ])
    );

return (new Config())
    ->withProfile($profile);
