<?php
/**
 * Railway PHP Built-in Server Entry Point
 * This file is used when running PHP's built-in server
 */

// Get the requested file path
$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'];

// Remove query string for file checking
$filePath = __DIR__ . $path;

// If it's a file that exists, serve it directly
if ($path !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false; // Let PHP serve the file
}

// Otherwise, route to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
