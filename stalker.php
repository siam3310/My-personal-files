<?php
/**
 * Stalker Portal to M3U Converter - FIXED VERSION
 * Complete working code with proper authentication
 */
error_reporting(0);
set_time_limit(0);
ob_clean();

class StalkerPortalToM3U {
    private $portal_url;
    private $mac;
    private $device_id;
    private $device_id2;
    private $serial_number;
    private $token = '';
    private $cookie_file;
    private $timezone = 'Europe/Moscow';
    
    public function __construct($portal_url, $mac, $device_id, $device_id2, $serial) {
        $this->portal_url = rtrim($portal_url, '/');
        $this->mac = strtoupper($mac);
        $this->device_id = $device_id;
        $this->device_id2 = $device_id2;
        $this->serial_number = $serial;
        $this->cookie_file = sys_get_temp_dir() . '/stalker_cookie_' . md5($mac) . '.txt';
        
        // Clean old cookie
        if (file_exists($this->cookie_file)) {
            unlink($this->cookie_file);
        }
    }
    
    /**
     * Get current timestamp in milliseconds
     */
    private function getMsTimestamp() {
        return round(microtime(true) * 1000);
    }
    
    /**
     * Generate random string
     */
    private function randomString($length = 8) {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }
    
    /**
     * Make HTTP request with proper headers
     */
    private function makeRequest($url, $post_data = null, $json = true) {
        $ch = curl_init();
        
        // Get timestamp for request
        $timestamp = time();
        $rand = $this->randomString(5);
        
        // MAG250 headers exactly as original STB sends them
        $headers = [
            'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 2.20.0_r5.2.0-e2k TV lay: 1920x1080',
            'X-User-Agent: Model: MAG250; Link: WiFi',
            'Accept: */*',
            'Accept-Language: ru',
            'Accept-Charset: utf-8',
            'Connection: Keep-Alive',
            'X-Real-IP: ' . $this->getRandomIP(),
            'X-Forwarded-For: ' . $this->getRandomIP()
        ];
        
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '&rand=' . $rand . '&_=' . $this->getMsTimestamp(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEFILE => $this->cookie_file,
            CURLOPT_COOKIEJAR => $this->cookie_file,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_REFERER => $this->portal_url . '/',
            CURLOPT_AUTOREFERER => true,
            CURLOPT_ENCODING => ''
        ]);
        
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        curl_close($ch);
        
        if ($json && strpos($content_type, 'json') !== false) {
            return json_decode($response, true);
        }
        
        return $response;
    }
    
    /**
     * Get random IP for headers
     */
    private function getRandomIP() {
        return rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    }
    
    /**
     * Perform handshake with portal
     */
    public function handshake() {
        // Step 1: Get profile
        $url = $this->portal_url . '/server/load.php?type=stb&action=get_profile&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $profile = $this->makeRequest($url);
        
        // Step 2: Handshake with MAC
        $url = $this->portal_url . '/server/load.php?type=stb&action=handshake&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $post = json_encode([
            'mac' => $this->mac,
            'device_id' => $this->device_id,
            'device_id2' => $this->device_id2,
            'serial_number' => $this->serial_number,
            'timezone' => $this->timezone,
            'auth_second_step' => true
        ]);
        
        $result = $this->makeRequest($url, $post);
        
        if (isset($result['js']) && isset($result['js']['token'])) {
            $this->token = $result['js']['token'];
            
            // Step 3: Authenticate with token
            $url = $this->portal_url . '/server/load.php?type=stb&action=do_auth&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
            
            $auth = $this->makeRequest($url, json_encode(['token' => $this->token]));
            
            if (isset($auth['js']) && isset($auth['js']['status']) && $auth['js']['status'] == 'OK') {
                
                // Step 4: Set profile
                $url = $this->portal_url . '/server/load.php?type=stb&action=set_profile&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
                
                $set_profile = $this->makeRequest($url, json_encode([
                    'device_info' => [
                        'device_id' => $this->device_id,
                        'device_id2' => $this->device_id2,
                        'signature' => $this->serial_number,
                        'serial_number' => $this->serial_number
                    ]
                ]));
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all channels with proper links
     */
    public function getAllChannels() {
        $all_channels = [];
        $page = 0;
        
        while (true) {
            $url = $this->portal_url . '/server/load.php?type=itv&action=get_all_channels&p=' . $page . '&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
            
            $result = $this->makeRequest($url);
            
            if (isset($result['js']) && isset($result['js']['data']) && !empty($result['js']['data'])) {
                $all_channels = array_merge($all_channels, $result['js']['data']);
                $page++;
                
                // Check if more pages
                if ($result['js']['total_items'] ?? 0 <= count($all_channels)) {
                    break;
                }
            } else {
                break;
            }
        }
        
        return $all_channels;
    }
    
    /**
     * Get working stream link
     */
    public function getStreamLink($cmd) {
        // Direct link if already http
        if (strpos($cmd, 'http') === 0) {
            return $cmd;
        }
        
        // Get stream link through portal
        $url = $this->portal_url . '/server/load.php?type=itv&action=create_link&cmd=' . urlencode($cmd) . '&for_pc=1&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $result = $this->makeRequest($url);
        
        if (isset($result['js']) && isset($result['js']['cmd'])) {
            $stream_cmd = $result['js']['cmd'];
            
            // Extract URL from ffmpeg command
            if (preg_match('/"(http[^"]+)"/', $stream_cmd, $matches)) {
                return $matches[1];
            }
            
            // If it's already a URL
            if (strpos($stream_cmd, 'http') === 0) {
                return $stream_cmd;
            }
            
            // Check if it's a token URL
            if (strpos($stream_cmd, '/ch/') !== false) {
                return $this->portal_url . $stream_cmd;
            }
        }
        
        return null;
    }
    
    /**
     * Generate M3U playlist
     */
    public function generateM3U() {
        $channels = $this->getAllChannels();
        
        $playlist = "#EXTM3U\n";
        $playlist .= '#EXT-X-VERSION:3' . "\n";
        
        foreach ($channels as $channel) {
            $name = $channel['name'] ?? 'Unknown';
            $cmd = $channel['cmd'] ?? '';
            
            if (empty($cmd)) continue;
            
            // Get working stream
            $stream_url = $this->getStreamLink($cmd);
            
            if ($stream_url) {
                $logo = isset($channel['logo']) ? $this->portal_url . '/' . ltrim($channel['logo'], '/') : '';
                $genre = $channel['tv_genre_name'] ?? $channel['genre'] ?? 'General';
                $epg_id = $channel['epg_id'] ?? '';
                
                $playlist .= '#EXTINF:-1 tvg-id="' . $epg_id . '" tvg-name="' . $name . '" tvg-logo="' . $logo . '" group-title="' . $genre . '",' . $name . "\n";
                $playlist .= $stream_url . "\n";
                
                // Small delay to avoid overload
                usleep(50000);
            }
        }
        
        return $playlist;
    }
    
    /**
     * Get EPG data
     */
    public function getEPG($epg_id, $period = 24) {
        $url = $this->portal_url . '/server/load.php?type=epg&action=get_simple_data&period=' . $period . '&epg_id=' . $epg_id . '&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $result = $this->makeRequest($url);
        
        return $result['js']['data'] ?? [];
    }
    
    /**
     * Get VOD categories
     */
    public function getVODCategories() {
        $url = $this->portal_url . '/server/load.php?type=vod&action=get_categories&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $result = $this->makeRequest($url);
        
        return $result['js'] ?? [];
    }
    
    /**
     * Get VOD list
     */
    public function getVODList($category_id = 0, $page = 0) {
        $url = $this->portal_url . '/server/load.php?type=vod&action=get_ordered_list&cat_id=' . $category_id . '&p=' . $page . '&JsHttpRequest=' . $this->getMsTimestamp() . '-xml';
        
        $result = $this->makeRequest($url);
        
        return $result['js'] ?? [];
    }
}

// Configuration
$portal_url = 'http://89.187.191.54/stalker_portal/c';
$mac = '00:1A:79:68:20:65';
$device_id = '67D53B801F7AFD2E30673BDB72E0C7FAFED2A380D8472D8F779BD729AAA756D3';
$device_id2 = '6046C528477A69EAF44086270764567F48ADFDF5459A749846FC3E54F1F3EEF2';
$serial = '17C6BD62410BA';

// Initialize converter
$converter = new StalkerPortalToM3U($portal_url, $mac, $device_id, $device_id2, $serial);

// Set headers for CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Perform handshake
if ($converter->handshake()) {
    
    $action = $_GET['action'] ?? 'channels';
    
    switch ($action) {
        case 'channels':
            if (isset($_GET['format']) && $_GET['format'] === 'json') {
                header('Content-Type: application/json');
                $channels = $converter->getAllChannels();
                echo json_encode([
                    'success' => true,
                    'channels' => $channels,
                    'count' => count($channels)
                ], JSON_PRETTY_PRINT);
            } else {
                header('Content-Type: audio/x-mpegurl');
                header('Content-Disposition: attachment; filename="playlist_' . date('Y-m-d') . '.m3u"');
                echo $converter->generateM3U();
            }
            break;
            
        case 'epg':
            header('Content-Type: application/json');
            $epg_id = $_GET['epg_id'] ?? '';
            $period = $_GET['period'] ?? 24;
            
            if ($epg_id) {
                $epg = $converter->getEPG($epg_id, $period);
                echo json_encode([
                    'success' => true,
                    'epg_id' => $epg_id,
                    'data' => $epg
                ], JSON_PRETTY_PRINT);
            } else {
                echo json_encode(['success' => false, 'error' => 'EPG ID required']);
            }
            break;
            
        case 'vod_categories':
            header('Content-Type: application/json');
            $categories = $converter->getVODCategories();
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'vod':
            header('Content-Type: application/json');
            $cat_id = $_GET['category_id'] ?? 0;
            $page = $_GET['page'] ?? 0;
            $vod = $converter->getVODList($cat_id, $page);
            echo json_encode([
                'success' => true,
                'data' => $vod
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'stream':
            $cmd = $_GET['cmd'] ?? '';
            if ($cmd) {
                $stream_url = $converter->getStreamLink($cmd);
                if ($stream_url) {
                    header('Location: ' . $stream_url);
                } else {
                    http_response_code(404);
                    echo 'Stream not found';
                }
            }
            break;
            
        default:
            echo "Available actions:\n";
            echo "- channels (format=m3u or json)\n";
            echo "- epg?epg_id=XXX\n";
            echo "- vod_categories\n";
            echo "- vod?category_id=X&page=X\n";
            echo "- stream?cmd=XXX\n";
    }
    
} else {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Authentication failed - Portal might be down or credentials invalid',
        'debug' => [
            'portal' => $portal_url,
            'mac' => $mac,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

// Clean up old cookie
if (file_exists($converter->cookie_file)) {
    unlink($converter->cookie_file);
}
?>
