<?php
/**
 * BACKDOOR FILE SCANNER
 * Searches for suspicious functions in PHP files
 * 
 * USAGE:
 * 1. Upload to: /home/femi9software/public_html/scanner_temp.php
 * 2. Set password below
 * 3. Access: https://yoursite.com/scanner_temp.php?password=YOUR_PASSWORD
 * 4. DELETE immediately after use!
 */

// ============================================================================
// SECURITY: Password Protection
// ============================================================================
$SCAN_PASSWORD = 'Abhi@dev';  // ← CHANGE THIS!

if (!isset($_GET['password']) || $_GET['password'] !== $SCAN_PASSWORD) {
    http_response_code(403);
    die('Access denied. Add ?password=YOUR_PASSWORD to URL');
}

// ============================================================================
// CONFIGURATION
// ============================================================================
$SCAN_DIR = '/home/femi9software/public_html/femi9/billing/';  // Directory to scan
$MAX_RESULTS = 500;  // Limit results to prevent browser crash

set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '512M');

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Scan directory recursively for PHP files
 */
function scanDirectory($dir) {
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $items = @scandir($dir);
    if (!$items) {
        return $files;
    }
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            $files = array_merge($files, scanDirectory($path));
        } elseif (is_file($path) && preg_match('/\.php$/i', $item)) {
            $files[] = $path;
        }
    }
    
    return $files;
}

/**
 * Search for suspicious patterns in a file
 */
function searchFile($filepath, $patterns) {
    $matches = [];
    
    if (!is_readable($filepath)) {
        return $matches;
    }
    
    $content = @file_get_contents($filepath);
    if ($content === false) {
        return $matches;
    }
    
    foreach ($patterns as $pattern => $description) {
        if (preg_match('/' . $pattern . '/i', $content, $found)) {
            $matches[] = [
                'pattern' => $pattern,
                'description' => $description,
                'sample' => substr($found[0], 0, 100)
            ];
        }
    }
    
    return $matches;
}

// ============================================================================
// SUSPICIOUS PATTERNS
// ============================================================================
$SUSPICIOUS_PATTERNS = [
    // Network operations
    'curl_exec\s*\(' => 'curl_exec() - Network request',
    'curl_init\s*\(' => 'curl_init() - cURL initialization',
    'wget' => 'wget command - File download',
    'file_get_contents\s*\(\s*["\']https?://' => 'file_get_contents() with URL',
    'fsockopen\s*\(' => 'fsockopen() - Network socket',
    
    // Command execution
    'shell_exec\s*\(' => 'shell_exec() - System command',
    'exec\s*\(' => 'exec() - Command execution',
    'system\s*\(' => 'system() - System command',
    'passthru\s*\(' => 'passthru() - Command execution',
    'proc_open\s*\(' => 'proc_open() - Process execution',
    'popen\s*\(' => 'popen() - Process execution',
    
    // Code execution
    'eval\s*\(' => 'eval() - Code execution',
    'assert\s*\(' => 'assert() - Code execution',
    'create_function\s*\(' => 'create_function() - Dynamic function',
    
    // Obfuscation
    'base64_decode\s*\(' => 'base64_decode() - Obfuscation',
    'gzinflate\s*\(' => 'gzinflate() - Compression obfuscation',
    'gzuncompress\s*\(' => 'gzuncompress() - Compression obfuscation',
    'str_rot13\s*\(' => 'str_rot13() - String obfuscation',
    
    // File operations
    'file_put_contents\s*\(\s*\$' => 'file_put_contents() with variable',
    'fwrite\s*\(\s*\$.*\$' => 'fwrite() with variables',
    'move_uploaded_file' => 'move_uploaded_file() - File upload',
    
    // Backdoor indicators
    '\$_GET\[.*\]\(' => 'Dynamic function from $_GET',
    '\$_POST\[.*\]\(' => 'Dynamic function from $_POST',
    '\$_REQUEST\[.*\]\(' => 'Dynamic function from $_REQUEST',
    '@eval' => 'Suppressed eval()',
    '@system' => 'Suppressed system()',
];

// ============================================================================
// MAIN SCAN
// ============================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backdoor Scanner Results</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .warning {
            background: #4a1515;
            border: 2px solid #f44336;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info {
            background: #2a2a2a;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        .result {
            background: #2d2d2d;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #ff9800;
            border-radius: 3px;
        }
        .file-path {
            color: #569cd6;
            font-weight: bold;
            word-break: break-all;
        }
        .pattern {
            color: #ce9178;
            font-style: italic;
        }
        .sample {
            background: #1e1e1e;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #3e3e3e;
            overflow-x: auto;
            font-size: 12px;
        }
        .stats {
            background: #252526;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .progress {
            color: #4ec9b0;
        }
    </style>
</head>
<body>

<h1>🔍 Backdoor File Scanner</h1>

<div class="warning">
    <strong>⚠️ CRITICAL:</strong> DELETE this scanner file immediately after use!
</div>

<div class="info">
    <strong>Scanning:</strong> <?php echo htmlspecialchars($SCAN_DIR); ?><br>
    <strong>Started:</strong> <?php echo date('Y-m-d H:i:s'); ?>
</div>

<?php
// Start scanning
$start_time = microtime(true);
$files = scanDirectory($SCAN_DIR);
$total_files = count($files);

echo "<div class='progress'>Found {$total_files} PHP files to scan...</div>";
flush();

$results = [];
$scanned = 0;

foreach ($files as $filepath) {
    $scanned++;
    
    // Show progress every 50 files
    if ($scanned % 50 === 0) {
        echo "<div class='progress'>Progress: {$scanned}/{$total_files} files...</div>";
        flush();
    }
    
    $matches = searchFile($filepath, $SUSPICIOUS_PATTERNS);
    
    if (!empty($matches)) {
        $relative_path = str_replace($SCAN_DIR, '', $filepath);
        $results[$relative_path] = $matches;
        
        // Stop if we hit max results
        if (count($results) >= $MAX_RESULTS) {
            echo "<div class='warning'>Reached maximum results limit ({$MAX_RESULTS}). Stopping scan.</div>";
            break;
        }
    }
}

$duration = round(microtime(true) - $start_time, 2);

// Display statistics
echo "<div class='stats'>";
echo "<h2>📊 Scan Statistics</h2>";
echo "<strong>Files scanned:</strong> {$scanned}<br>";
echo "<strong>Files with suspicious patterns:</strong> " . count($results) . "<br>";
echo "<strong>Scan duration:</strong> {$duration} seconds<br>";
echo "</div>";

// Display results
if (empty($results)) {
    echo "<div class='info'>";
    echo "<h2>✅ No Suspicious Patterns Found</h2>";
    echo "<p>The scanner didn't detect common backdoor patterns. However:</p>";
    echo "<ul>";
    echo "<li>This doesn't guarantee 100% security</li>";
    echo "<li>Sophisticated backdoors may evade detection</li>";
    echo "<li>Manual review of suspicious files is still recommended</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<h2>⚠️ Suspicious Files Found (" . count($results) . ")</h2>";
    echo "<p>Review these files carefully. Not all matches are backdoors - some may be legitimate code.</p>";
    
    foreach ($results as $file => $matches) {
        echo "<div class='result'>";
        echo "<div class='file-path'>📄 {$file}</div>";
        
        foreach ($matches as $match) {
            echo "<div style='margin-top: 10px;'>";
            echo "<strong>Pattern:</strong> <span class='pattern'>{$match['description']}</span><br>";
            echo "<strong>Found:</strong>";
            echo "<div class='sample'>" . htmlspecialchars($match['sample']) . "</div>";
            echo "</div>";
        }
        
        echo "</div>";
    }
}
?>

<div class="warning">
    <h2>⚡ Next Steps</h2>
    <ol>
        <li><strong>DELETE this scanner file NOW</strong></li>
        <li>Review each file listed above</li>
        <li>Check if the functions are used legitimately</li>
        <li>Look for obfuscated or suspicious code</li>
        <li>Compare with backup versions (if available)</li>
        <li>Remove any confirmed backdoors</li>
    </ol>
</div>

<div class="info">
    <strong>Scan completed:</strong> <?php echo date('Y-m-d H:i:s'); ?>
</div>

</body>
</html>