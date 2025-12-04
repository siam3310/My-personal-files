<?php
$XTREAM_URL = "http://opplex.to/get.php?username=CentMPT&password=411678&type=m3u_plus&output=m3u8";

$url = parse_url($XTREAM_URL);
parse_str($url['query'], $q);
$username = $q['username'];
$password = $q['password'];
$server = $url['scheme'] . "://" . $url['host'];
$baseApi = "$server/player_api.php?username=$username&password=$password";

function getData($url){ return json_decode(file_get_contents($url), true); }

$mode = $_GET['mode'] ?? 'categories';
$cat_id = $_GET['cat'] ?? null;
$stream_id = $_GET['id'] ?? null;
$search = $_GET['s'] ?? '';

$bg = "#16161a";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>XtreamTV</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: <?= $bg ?>; color: #fff; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand fw-bold" href="/">XtreamTV PHP</a>
    <form class="d-flex" method="get">
        <input type="hidden" name="mode" value="search">
        <input class="form-control me-2" type="search" placeholder="Search channels..." aria-label="Search" name="s" value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-light" type="submit">Search</button>
    </form>
</nav>

<div class="container mt-4">

<?php
if($mode === 'search' && $search){
    $all = getData("$baseApi&action=get_live_streams");
    $found = array_filter($all, fn($c)=> stripos($c['name'], $search) !== false);
    echo "<h5 class='mb-3'>Search Results</h5><div class='row row-cols-2 row-cols-md-4 g-3'>";
    foreach($found as $ch){
        echo "<div class='col'>
                <div class='card bg-dark text-white'>
                    <img src='{$ch['stream_icon']}' class='card-img-top' onerror=\"this.src=''\" alt='Channel'>
                    <div class='card-body p-2'>
                        <a href='?mode=play&id={$ch['stream_id']}' class='stretched-link text-white text-decoration-none fw-bold'>{$ch['name']}</a>
                    </div>
                </div>
              </div>";
    }
    echo "</div>";
    echo "<a href='/' class='btn btn-light mt-3'>Back</a></div></body></html>";
    exit;
}

if($mode === 'categories'){
    $cats = getData("$baseApi&action=get_live_categories");
    echo "<h5 class='mb-3'>Categories</h5><div class='row row-cols-2 row-cols-md-4 g-3'>";
    foreach($cats as $c){
        echo "<div class='col'>
                <div class='card bg-dark text-white text-center p-2'>
                    <a href='?mode=channels&cat={$c['category_id']}' class='text-white text-decoration-none fw-bold'>{$c['category_name']}</a>
                </div>
              </div>";
    }
    echo "</div>";
}

if($mode === 'channels' && $cat_id){
    echo "<a href='/' class='btn btn-light mb-3'>← Back</a>";
    $chs = getData("$baseApi&action=get_live_streams&category_id=$cat_id");
    echo "<div class='row row-cols-2 row-cols-md-4 g-3'>";
    foreach($chs as $ch){
        echo "<div class='col'>
                <div class='card bg-dark text-white'>
                    <img src='{$ch['stream_icon']}' class='card-img-top' onerror=\"this.src=''\" alt='Channel'>
                    <div class='card-body p-2'>
                        <a href='?mode=play&id={$ch['stream_id']}&cat=$cat_id' class='stretched-link text-white text-decoration-none fw-bold'>{$ch['name']}</a>
                    </div>
                </div>
              </div>";
    }
    echo "</div>";
}

if($mode === 'play' && $stream_id){
    $streamUrl = "$server/live/$username/$password/$stream_id.m3u8";
    $epg = getData("$baseApi&action=get_short_epg&stream_id=$stream_id&limit=6");
?>
<div class="card bg-dark text-white p-3">
    <video src="<?= $streamUrl ?>" controls autoplay class="w-100 mb-3"></video>
    <a href="/" class="btn btn-light w-100 mb-3">Close</a>

    <h6>EPG</h6>
    <?php
    if(isset($epg['epg_listings'])){
        foreach($epg['epg_listings'] as $e){
            echo "<div class='mb-2'>
                    <strong>" . base64_decode($e['title']) . "</strong><br>
                    <small>".date('h:i A', strtotime($e['start']))." - ".date('h:i A', strtotime($e['end']))."</small>
                  </div>";
        }
    } else echo "<p>No EPG</p>";
    ?>
</div>
<?php } ?>

</div>
</body>
</html>
