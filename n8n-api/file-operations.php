<?php
declare(strict_types=1);

/**
 * Simple file operations API (list / read) with improved security and error handling.
 *
 * Notes:
 * - Put your secret in an environment variable (recommended) and not in code.
 * - This file intentionally keeps output JSON and avoids exposing stack traces in production.
 */

const BASE_DIR = '/home/billing0femi9/public_html'; // consider moving to env
// Prefer getenv('API_SECRET') in production
const API_SECRET_FALLBACK = '1234';

header('Content-Type: application/json; charset=utf-8');

/**
 * Send JSON response and exit
 */
function send_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Retrieve bearer token from request headers (robust fallback)
 */
function get_bearer_token(): ?string
{
    // Try common server variables
    $authHeader = null;
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } else {
        // Some SAPI expose all headers here
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    $authHeader = $v;
                    break;
                }
            }
        }
    }

    if ($authHeader === null) {
        return null;
    }

    if (stripos($authHeader, 'bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }

    return null;
}

/**
 * Resolve and validate a requested path under BASE_DIR.
 * Prevents path traversal by comparing realpath prefixes.
 */
function resolve_requested_path(string $requestedPath): string
{
    // Normalize and reject NUL bytes
    $requestedPath = str_replace("\0", '', $requestedPath);
    $requestedPath = trim($requestedPath);

    // Construct candidate path (do not allow absolute paths outside BASE_DIR)
    $candidate = rtrim(BASE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($requestedPath, DIRECTORY_SEPARATOR);

    $baseReal = realpath(BASE_DIR);
    $real = realpath($candidate);

    if ($baseReal === false) {
        // Critical server config issue
        send_json(['error' => 'Server configuration error'], 500);
    }

    // If realpath failed (non-existing path) we still want to reject if it escapes base
    if ($real === false) {
        // If candidate is a subpath that does not exist yet (e.g. listing non-existing dir),
        // we can still compute a normalized path and ensure it doesn't try to escape BASE_DIR.
        $normalized = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($requestedPath, DIRECTORY_SEPARATOR);
        $normalizedReal = realpath(dirname($normalized)) ?: null;
        if ($normalizedReal === null || strpos($normalizedReal, $baseReal) !== 0) {
            send_json(['error' => 'Invalid path'], 403);
        }
        // Return the candidate (non-resolvable yet) - caller must check is_file/is_dir afterwards
        return $normalized;
    }

    // Ensure requested path is inside base directory
    if (strpos($real, $baseReal) !== 0) {
        send_json(['error' => 'Invalid path'], 403);
    }

    return $real;
}

/**
 * Is file binary-ish? If binary, we'll return base64 content rather than raw text.
 */
function is_binary_file(string $filename): bool
{
    if (!is_file($filename) || !is_readable($filename)) {
        return false;
    }

    // Use finfo to get mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return false;
    }
    $mime = finfo_file($finfo, $filename);
    finfo_close($finfo);

    if ($mime === false) {
        return false;
    }

    // Consider text/* and application/json, application/javascript as text
    if (str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/javascript'], true)) {
        return false;
    }

    return true;
}

/**
 * Main execution
 */
try {
    // Authentication
    $token = get_bearer_token();
    $expected = getenv('API_SECRET') ?: API_SECRET_FALLBACK;

    if ($token === null || !hash_equals((string)$expected, (string)$token)) {
        send_json(['error' => 'Unauthorized'], 401);
    }

    // Read JSON body safely
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        send_json(['error' => 'Invalid JSON input'], 400);
    }

    $action = $data['action'] ?? '';
    $path = $data['path'] ?? '';

    if (!is_string($action) || $action === '') {
        send_json(['error' => 'Missing action'], 400);
    }

    // Allowed actions: list, read
    if ($action === 'list') {
        $realPath = resolve_requested_path((string)$path);

        if (!is_dir($realPath)) {
            send_json(['error' => 'Not a directory'], 400);
        }

        $items = [];
        // Use DirectoryIterator for efficiency and to avoid loading large arrays into memory
        $it = new DirectoryIterator($realPath);
        foreach ($it as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $isDir = $entry->isDir();
            $items[] = [
                'name'       => $entry->getFilename(),
                'type'       => $isDir ? 'dir' : 'file',
                'size'       => $isDir ? null : $entry->getSize(),
                'modified'   => $entry->getMTime(),
            ];
        }

        send_json(['success' => true, 'files' => $items], 200);
    }

    if ($action === 'read') {
        $realPath = resolve_requested_path((string)$path);

        if (!is_file($realPath) || !is_readable($realPath)) {
            send_json(['error' => 'Not a readable file'], 400);
        }

        // Limit read size to avoid huge payloads (example: 5 MB)
        $maxReadBytes = 5 * 1024 * 1024; // 5 MiB
        $fileSize = filesize($realPath);
        if ($fileSize === false) {
            send_json(['error' => 'Unable to determine file size'], 500);
        }
        if ($fileSize > $maxReadBytes) {
            send_json(['error' => 'File too large to read via API'], 413);
        }

        $binary = is_binary_file($realPath);
        $content = file_get_contents($realPath);
        if ($content === false) {
            send_json(['error' => 'Failed to read file'], 500);
        }

        if ($binary) {
            // return base64 to avoid corrupting JSON / transport
            send_json([
                'success'   => true,
                'is_binary' => true,
                'encoding'  => 'base64',
                'content'   => base64_encode($content),
            ], 200);
        }

        // For text, return raw content (safe json encoding will escape characters)
        send_json([
            'success'   => true,
            'is_binary' => false,
            'content'   => $content,
        ], 200);
    }

    send_json(['error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    // Do not expose internal details in production. Log the error server-side.
    error_log('File API error: ' . $e->getMessage());
    send_json(['error' => 'Internal server error'], 500);
}
