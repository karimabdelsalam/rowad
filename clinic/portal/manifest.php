<?php
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/manifest+json; charset=UTF-8');

$name = setting('clinic_name', 'عيادة التغذية');
$icon = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">'
    . '<rect width="192" height="192" rx="42" fill="#0f766e"/>'
    . '<text x="96" y="130" font-size="104" text-anchor="middle">🍏</text></svg>'
);

echo json_encode([
    'name'             => $name,
    'short_name'       => mb_substr($name, 0, 12),
    'description'      => 'بوابة المرضى — المتابعة والنظام الغذائي والمواعيد',
    'start_url'        => 'index.php',
    'scope'            => './',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#f1f5f9',
    'theme_color'      => '#0f766e',
    'dir'              => 'rtl',
    'lang'             => 'ar',
    'icons'            => [
        ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
        ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
