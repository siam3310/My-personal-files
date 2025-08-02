<?php 

function curl($url) { 
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ExpertSKB/1.0)'); 
    $response = curl_exec($ch); 
    curl_close($ch); 
    return $response; 
} 

header('Content-Type: application/vnd.apple.mpegurl'); 
header('Access-Control-Allow-Origin: *'); 

// Define your streams
$streams = [
    1 => "https://dzkyvlfyge.erbvr.com/PeaceTvEnglish/tracks-v5a1/mono.m3u8?sid=7SPIDE_gSUCIPf-4iUwhxg&bvr_flags=1&bvr_ch=PeaceTvEnglish&bvr_bw=570000",
    2 => "https://dzkyvlfyge.erbvr.com/PeaceTvBangla/tracks-v4a1/mono.m3u8?sid=RID8TrhLYkybmyrNs_iTog&bvr_flags=1&bvr_ch=PeaceTvBangla&bvr_bw=500000",
    3 => "https://dzkyvlfyge.erbvr.com/PeaceTvUrdu/tracks-v5a1/mono.m3u8?sid=ZsCnHde_xkW4lm0qDCR4ow&bvr_flags=1&bvr_ch=PeaceTvUrdu&bvr_bw=520000",
];

// Get the channel parameter from the query string
$channel = isset($_GET['channel']) ? intval($_GET['channel']) : null; // Set to null if not specified

if ($channel === null) {
    // Redirect to the main site if no channel is specified
    header("Location: https://api.hideme.eu.org");
    exit; // Ensure script execution stops after the redirect
}

// Validate the channel and get the corresponding stream URL
if (array_key_exists($channel, $streams)) {
    $streamUrl = $streams[$channel];
    echo curl($streamUrl);
} else {
    // Handle the case where the channel does not exist
    header("HTTP/1.0 404 Not Found");
    echo "Stream not found.";
}
?>
