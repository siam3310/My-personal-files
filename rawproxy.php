<?php
error_reporting(0);
set_time_limit(0);
ob_start();

function b64_to_hex($b64) {
    return bin2hex($b64);
}

function hex_to_b64($hex) {
    $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
    if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
    return hex2bin($hex);
}

function smart_decode($input) {
    $input = trim($input);
    
    // Try hex decode first
    if (ctype_xdigit($input) && strlen($input) > 20) {
        $b64 = hex_to_b64($input);
        if ($b64) {
            $decoded = base64_decode($b64, true);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
                return $decoded;
            }
        }
    }
    
    // Try base64
    $b64check = base64_decode($input, true);
    if ($b64check && filter_var($b64check, FILTER_VALIDATE_URL)) {
        return $b64check;
    }
    
    // Try URL decode
    $urldecoded = urldecode($input);
    if (filter_var($urldecoded, FILTER_VALIDATE_URL)) {
        return $urldecoded;
    }
    
    return $input;
}

function get_base($url) {
    $p = parse_url($url);
    $scheme = $p['scheme'] ?? 'http';
    $host = $p['host'] ?? '';
    $port = isset($p['port']) ? ':' . $p['port'] : '';
    $path = $p['path'] ?? '/';
    $dir = rtrim(dirname($path), '/') . '/';
    return $scheme . '://' . $host . $port . $dir;
}

function to_abs($base, $rel) {
    if (preg_match('#^https?://#i', $rel)) return $rel;
    if (strpos($rel, '//') === 0) return 'https:' . $rel;
    if (strpos($rel, '/') === 0) {
        $p = parse_url($base);
        $scheme = $p['scheme'] ?? 'http';
        $host = $p['host'] ?? '';
        $port = isset($p['port']) ? ':' . $p['port'] : '';
        return $scheme . '://' . $host . $port . $rel;
    }
    return $base . $rel;
}

function multi_curl_fetch($url, $depth = 0) {
    $max_redirects = 10;
    if ($depth > $max_redirects) return false;
    
    $ua_list = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1'
    ];
    
    $ch = curl_init();
    $ua = $ua_list[array_rand($ua_list)];
    
    $headers = [
        'User-Agent: ' . $ua,
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
        'Accept-Encoding: identity',
        'Connection: keep-alive',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Referer: ' . $url,
        'Origin: ' . parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST)
    ];
    
    // Parse URL for potential auth
    $parsed = parse_url($url);
    if (isset($parsed['user']) || isset($parsed['pass'])) {
        $auth = ($parsed['user'] ?? '') . ':' . ($parsed['pass'] ?? '');
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_BUFFERSIZE => 131072,
        CURLOPT_TCP_FASTOPEN => true,
        CURLOPT_TCP_NODELAY => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    
    curl_close($ch);
    
    if ($response === false) return false;
    
    // Handle redirects
    if (in_array($http_code, [301, 302, 303, 307, 308]) && $redirect_url) {
        return multi_curl_fetch(to_abs($url, $redirect_url), $depth + 1);
    }
    
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    return [
        'headers' => $headers,
        'body' => $body,
        'content_type' => $content_type,
        'http_code' => $http_code
    ];
}

function process_m3u8($body, $base_url, $original_url) {
    $lines = preg_split('/\r\n|\n|\r/', $body);
    $result = [];
    $processed_keys = [];
    
    foreach ($lines as $line) {
        $line = rtrim($line);
        
        if (strlen($line) === 0) {
            $result[] = '';
            continue;
        }
        
        // Handle tags
        if ($line[0] === '#') {
            // Process EXT-X-KEY
            if (stripos($line, '#EXT-X-KEY') === 0 && preg_match('/URI="([^"]+)"/', $line, $matches)) {
                $key_uri = $matches[1];
                
                // Handle relative/absolute key URIs
                $abs_key = to_abs($base_url, $key_uri);
                
                // Generate proxy URL with multiple encoding layers
                $encoded = base64_encode($abs_key);
                $hexed = bin2hex($encoded);
                $proxy = '?url=' . $hexed;
                
                // Double-encode for extra obfuscation
                if (!isset($processed_keys[$abs_key])) {
                    $processed_keys[$abs_key] = true;
                }
                
                $line = preg_replace('/URI="([^"]+)"/', 'URI="' . $proxy . '"', $line);
            }
            
            // Handle EXT-X-MAP
            if (stripos($line, '#EXT-X-MAP') === 0 && preg_match('/URI="([^"]+)"/', $line, $matches)) {
                $map_uri = $matches[1];
                $abs_map = to_abs($base_url, $map_uri);
                $proxy = '?url=' . bin2hex(base64_encode($abs_map));
                $line = preg_replace('/URI="([^"]+)"/', 'URI="' . $proxy . '"', $line);
            }
            
            $result[] = $line;
            continue;
        }
        
        // Handle URLs
        $abs = to_abs($base_url, $line);
        $proxy = '?url=' . bin2hex(base64_encode($abs));
        $result[] = $proxy;
    }
    
    return implode("\n", $result);
}

// Main execution
if (!isset($_GET['url'])) {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?url=') !== false) {
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $params);
        if (isset($params['url'])) {
            $_GET['url'] = $params['url'];
        }
    }
    
    if (!isset($_GET['url'])) {
        http_response_code(400);
        header('Content-Type: text/plain');
        die("Error: URL parameter required\nUsage: " . $_SERVER['PHP_SELF'] . "?url=[encoded_url]");
    }
}

$raw_url = $_GET['url'];
$decoded_url = smart_decode($raw_url);

if (!filter_var($decoded_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die("Error: Invalid URL format");
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Max-Age: 86400');
    exit(0);
}

// Fetch the content
$fetch_result = multi_curl_fetch($decoded_url);

if ($fetch_result === false) {
    http_response_code(502);
    die("Error: Failed to fetch URL");
}

$body = $fetch_result['body'];
$content_type = $fetch_result['content_type'];
$http_code = $fetch_result['http_code'];

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: Content-Type, Content-Length, Content-Range');

// Check if it's M3U8
$is_m3u8 = false;
if ($content_type && stripos($content_type, 'mpegurl') !== false) $is_m3u8 = true;
if (stripos($content_type, 'vnd.apple.mpegurl') !== false) $is_m3u8 = true;
if (strpos($body, '#EXTM3U') !== false) $is_m3u8 = true;

if ($is_m3u8) {
    $base = get_base($decoded_url);
    $processed = process_m3u8($body, $base, $decoded_url);
    
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Disposition: inline');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $processed;
    exit;
}

// Handle other content types
if ($content_type) {
    header('Content-Type: ' . $content_type);
} else {
    // Try to detect by extension
    $ext = strtolower(pathinfo(parse_url($decoded_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $content_map = [
        'ts' => 'video/mp2t',
        'm4s' => 'video/iso.segment',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mkv' => 'video/x-matroska',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'key' => 'application/octet-stream',
        'm3u8' => 'application/vnd.apple.mpegurl',
        'm3u' => 'audio/x-mpegurl'
    ];
    
    if (isset($content_map[$ext])) {
        header('Content-Type: ' . $content_map[$ext]);
    } else {
        header('Content-Type: application/octet-stream');
    }
}

// Add content length if possible
$body_length = strlen($body);
if ($body_length > 0) {
    header('Content-Length: ' . $body_length);
}

// Support range requests
if (isset($_SERVER['HTTP_RANGE'])) {
    header('Accept-Ranges: bytes');
    // Basic range handling can be added here if needed
}

// Output the content
echo $body;
exit;
?>
