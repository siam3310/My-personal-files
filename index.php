<?php
/**
 * Stalker Portal to M3U Converter
 * For educational purposes only - Understanding Stalker Middleware
 */

class StalkerPortalToM3U {
    private $portal_url;
    private $mac;
    private $device_id;
    private $device_id2;
    private $signature;
    private $serial_number;
    private $cookies = [];
    private $token = '';
    
    public function __construct($portal_url, $mac, $device_id, $device_id2, $serial) {
        $this->portal_url = rtrim($portal_url, '/');
        $this->mac = strtoupper($mac);
        $this->device_id = $device_id;
        $this->device_id2 = $device_id2;
        $this->serial_number = $serial;
        $this->signature = $this->generateSignature();
    }
    
    /**
     * Generate signature based on device info
     */
    private function generateSignature() {
        // Stalker signature algorithm
        $data = $this->mac . $this->device_id . $this->device_id2 . $this->serial_number;
        return strtoupper(hash('sha256', $data));
    }
    
    /**
     * Make HTTP request with proper headers
     */
    private function makeRequest($url, $post_data = null) {
        $ch = curl_init();
        
        $headers = [
            'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2.20.0_r5.2.0-e2k TV lay: 1920x1080',
            'X-User-Agent: Model: MAG250; Link: WiFi',
            'Accept: application/json',
            'Accept-Language: ru_RU',
            'Connection: keep-alive'
        ];
        
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => 'cookies.txt',
            CURLOPT_COOKIEFILE => 'cookies.txt',
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        // Extract cookies
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $this->cookies = array_merge($this->cookies, $cookie);
        }
        
        curl_close($ch);
        
        return json_decode($body, true);
    }
    
    /**
     * Handshake with portal
     */
    public function handshake() {
        $url = $this->portal_url . '/server/load.php?type=stb&action=handshake';
        
        $post = json_encode([
            'mac' => $this->mac,
            'device_id' => $this->device_id,
            'device_id2' => $this->device_id2,
            'serial_number' => $this->serial_number,
            'signature' => $this->signature,
            'auth_second_step' => true
        ]);
        
        $result = $this->makeRequest($url, $post);
        
        if (isset($result['js'])) {
            $this->token = $result['js']['token'] ?? '';
            return true;
        }
        
        return false;
    }
    
    /**
     * Get profile information
     */
    public function getProfile() {
        $url = $this->portal_url . '/server/load.php?type=stb&action=get_profile';
        return $this->makeRequest($url);
    }
    
    /**
     * Get all channels
     */
    public function getAllChannels() {
        $channels = [];
        $page = 0;
        
        do {
            $url = $this->portal_url . '/server/load.php?type=itv&action=get_all_channels&force_ch_link_check=&JsHttpRequest=' . time() . '-xml';
            
            $result = $this->makeRequest($url);
            
            if (isset($result['js']['data'])) {
                $channels = array_merge($channels, $result['js']['data']);
                $page++;
            } else {
                break;
            }
            
        } while (count($result['js']['data']) > 0);
        
        return $channels;
    }
    
    /**
     * Get channel link
     */
    public function getChannelLink($cmd) {
        $url = $this->portal_url . '/server/load.php?type=itv&action=create_link&cmd=' . urlencode($cmd) . '&for_pc=true&JsHttpRequest=' . time() . '-xml';
        
        $result = $this->makeRequest($url);
        
        if (isset($result['js']['cmd'])) {
            // Decode the link
            $cmd = $result['js']['cmd'];
            if (strpos($cmd, 'ffmpeg') !== false) {
                preg_match('/http[^\s]+/', $cmd, $matches);
                return $matches[0] ?? null;
            }
            return $cmd;
        }
        
        return null;
    }
    
    /**
     * Generate M3U playlist
     */
    public function generateM3U() {
        echo "#EXTM3U\n";
        
        $channels = $this->getAllChannels();
        
        foreach ($channels as $channel) {
            $name = $channel['name'] ?? 'Unknown';
            $cmd = $channel['cmd'] ?? '';
            
            if (empty($cmd)) continue;
            
            // Get working stream link
            $link = $this->getChannelLink($cmd);
            
            if ($link) {
                $logo = $channel['logo'] ?? '';
                $epg_id = $channel['epg_id'] ?? '';
                
                echo "#EXTINF:-1 tvg-id=\"{$epg_id}\" tvg-logo=\"{$logo}\" group-title=\"" . ($channel['tv_genre_id'] ?? 'General') . "\",{$name}\n";
                echo $link . "\n";
            }
            
            // Small delay to avoid flooding
            usleep(100000);
        }
    }
    
    /**
     * Generate M3U8 playlist for streaming
     */
    public function generateM3U8Stream() {
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Access-Control-Allow-Origin: *');
        
        echo "#EXTM3U\n";
        echo "#EXT-X-VERSION:3\n";
        echo "#EXT-X-TARGETDURATION:10\n";
        echo "#EXT-X-MEDIA-SEQUENCE:0\n";
        
        $channels = $this->getAllChannels();
        $count = 0;
        
        foreach ($channels as $channel) {
            if ($count >= 10) break; // Limit for demo
            
            $cmd = $channel['cmd'] ?? '';
            if (empty($cmd)) continue;
            
            $link = $this->getChannelLink($cmd);
            
            if ($link) {
                echo "#EXTINF:10,{$channel['name']}\n";
                echo $link . "\n";
                $count++;
            }
        }
    }
    
    /**
     * Get EPG data
     */
    public function getEPG($epg_id, $period = 24) {
        $url = $this->portal_url . '/server/load.php?type=epg&action=get_simple_data&period=' . $period . '&epg_id=' . $epg_id;
        return $this->makeRequest($url);
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

// Perform handshake
if ($converter->handshake()) {
    
    // Check request type
    $format = $_GET['format'] ?? 'm3u';
    
    switch ($format) {
        case 'm3u':
            header('Content-Type: audio/x-mpegurl');
            header('Content-Disposition: attachment; filename="playlist.m3u"');
            $converter->generateM3U();
            break;
            
        case 'm3u8':
            $converter->generateM3U8Stream();
            break;
            
        case 'json':
            header('Content-Type: application/json');
            $channels = $converter->getAllChannels();
            echo json_encode(['channels' => $channels], JSON_PRETTY_PRINT);
            break;
            
        case 'epg':
            header('Content-Type: application/json');
            $epg_id = $_GET['epg_id'] ?? '';
            if ($epg_id) {
                $epg = $converter->getEPG($epg_id);
                echo json_encode(['epg' => $epg], JSON_PRETTY_PRINT);
            }
            break;
            
        default:
            echo "Usage: ?format=m3u|m3u8|json|epg";
    }
    
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication failed']);
}

/**
 * Additional features:
 * 
 * 1. Stream proxy with the previous code merged
 * 2. EPG caching
 * 3. Channel categories/groups
 * 4. Multi-portal support
 * 5. VOD content
 * 6. Catchup/archive
 */
?>
