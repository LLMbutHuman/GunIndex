<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$root = dirname(__DIR__, 2);
function bundled_package_refs(array $packages): array
{
    $result = [];
    foreach ($packages as $package) {
        $name = $package['name'];
        $ref = $package['dist']['reference'] ?? null;
        if ($ref === null || isset($result[$name])) throw new RuntimeException('Invalid bundled package metadata.');
        $result[$name] = [$package['version'], $ref];
    }
    ksort($result);
    return $result;
}
$lock = json_decode(file_get_contents($root.'/composer.lock'), true, 64, JSON_THROW_ON_ERROR);
$installed = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true, 64, JSON_THROW_ON_ERROR);
if (($installed['dev'] ?? true) !== false || bundled_package_refs($lock['packages']) !== bundled_package_refs($installed['packages'])) {
    throw new RuntimeException('Bundled PHP dependencies do not match composer.lock.');
}
require $root.'/vendor/autoload.php';
foreach ([Dompdf\Dompdf::class, lbuchs\WebAuthn\WebAuthn::class, PHPMailer\PHPMailer\PHPMailer::class] as $class) {
    if (!class_exists($class)) throw new RuntimeException('Bundled dependency did not load: '.$class);
}
