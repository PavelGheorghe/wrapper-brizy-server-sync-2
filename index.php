<?php

define('MAX_FILE_SIZE', 6000000);
// error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
// ini_set('display_errors', '0');

/*
 * Root dispatcher:
 * - routes cloud update calls to download_new_version.php
 * - serves clean URLs from active standalone version folder
 */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#/cloud/update-version/?$#', $requestPath) === 1) {
    require __DIR__ . '/download_new_version.php';
    exit;
}

$rootConfigPath = __DIR__ . '/config.json';
if (!is_file($rootConfigPath)) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Missing root config.json';
    exit;
}

$rootConfig = json_decode((string)@file_get_contents($rootConfigPath), true);
$activeVersion = is_array($rootConfig) ? (string)($rootConfig['active_version'] ?? '') : '';
if ($activeVersion === '' || preg_match('/^\d+(?:\.\d+)*$/', $activeVersion) !== 1) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Invalid active_version in config.json';
    exit;
}

$activeIndexPath = __DIR__ . '/' . $activeVersion . '/index.php';

if (!is_file($activeIndexPath)) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Active version entrypoint not found';
    exit;
}

$requirementsMarkerPath = __DIR__ . '/var/requirements-ok.json';
$markerPayload = null;
if (is_file($requirementsMarkerPath)) {
    $decodedMarker = json_decode((string)@file_get_contents($requirementsMarkerPath), true);
    if (
        is_array($decodedMarker)
        && (int)($decodedMarker['checker_version'] ?? 0) >= 3
        && (string)($decodedMarker['active_version'] ?? '') === $activeVersion
    ) {
        $markerPayload = $decodedMarker;
    }
}

if ($markerPayload === null) {
    require __DIR__ . '/requirements-checker/Requirement.php';
    require __DIR__ . '/requirements-checker/RequirementCollection.php';
    require __DIR__ . '/requirements-checker/RootProjectRequirements.php';

    $projectRootDir = __DIR__;
    $requirementsPassed = require __DIR__ . '/requirements-checker/requirements-checker.php';

    if ($requirementsPassed === true) {
        $markerDir = dirname($requirementsMarkerPath);
        if (!is_dir($markerDir)) {
            @mkdir($markerDir, 0777, true);
        }
        @file_put_contents(
            $requirementsMarkerPath,
            json_encode([
                'checker_version' => 3,
                'active_version' => $activeVersion,
                'checked_at' => gmdate('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}

require_once __DIR__ . '/daily_sync_version_check.php';
brz_run_daily_sync_version_check($rootConfigPath, $rootConfig, $activeVersion, $activeIndexPath);

if (strpos($requestPath, '/var/') === 0) {
    $allowedVarRootPath = realpath(__DIR__ . '/' . $activeVersion . '/var');
    $targetPath = __DIR__ . '/' . $activeVersion . '/' . ltrim($requestPath, '/');
    $resolvedTargetPath = realpath($targetPath);

    $isAllowedPath = $allowedVarRootPath !== false
        && $resolvedTargetPath !== false
        && strpos($resolvedTargetPath, $allowedVarRootPath . DIRECTORY_SEPARATOR) === 0
        && is_file($resolvedTargetPath);

    if (!$isAllowedPath) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Not Found';
        exit;
    }

    $extension = strtolower(pathinfo($resolvedTargetPath, PATHINFO_EXTENSION));
    if ($extension === 'php' || $extension === 'phtml' || $extension === 'phar') {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Not Found';
        exit;
    }

    $mimeByExtension = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'xml' => 'application/xml; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'htm' => 'text/html; charset=UTF-8',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    if (isset($mimeByExtension[$extension])) {
        $mimeType = $mimeByExtension[$extension];
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($resolvedTargetPath) ?: 'application/octet-stream';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($resolvedTargetPath));
    readfile($resolvedTargetPath);
    exit;
}

require $activeIndexPath;