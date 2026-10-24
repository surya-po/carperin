<?php
/**
 * Vercel Serverless Front Controller for CARPERIN
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = trim($uri, '/');

// Routing table
switch ($uri) {
    case 'admin':
    case 'admin.php':
        require __DIR__ . '/../admin.php';
        break;

    case 'login':
    case 'login.php':
        require __DIR__ . '/../login.php';
        break;

    case 'logout':
    case 'logout.php':
        require __DIR__ . '/../logout.php';
        break;

    case 'produk':
    case 'produk.php':
        require __DIR__ . '/../produk.php';
        break;

    case 'laporan':
    case 'laporan.php':
        require __DIR__ . '/../laporan.php';
        break;

    case 'order-detail':
    case 'order-detail.php':
        require __DIR__ . '/../order-detail.php';
        break;

    default:
        require __DIR__ . '/../index.php';
        break;
}
