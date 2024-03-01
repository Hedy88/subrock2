<?php

use function ChenTube\GetHostURL;

require_once($_SERVER['DOCUMENT_ROOT'] . '/lib/common.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

$router = new \Bramus\Router\Router();
$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader($_SERVER['DOCUMENT_ROOT'] . '/templates'),
    [
        'cache' => $_SERVER['DOCUMENT_ROOT'] . '/.twigcache',
        'debug' => true,
    ]
);

foreach (glob('lib/twig/*.php') as $file) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/' . $file;
}

if (isset($_COOKIE['token'])) {
    $_SESSION['token'] = $_COOKIE['token'];
}

if (isset($_SESSION['token'])) {
    $_SESSION['loggedIn'] = true;
    $__session_user = $db->query("SELECT * FROM  users WHERE token = :token", [':token' => $_SESSION['token']]);
    if ($__session_user->rowCount() == 0) {
        unset($_SESSION['token']);

        setcookie(
            "token",
            '',
            0,
            '/'
        );

        $_SESSION['loggedIn'] = false;
    } else {
        $__session_user_f =  $__session_user->fetch();

        $twig->addGlobal('__session_user', $__session_user_f);

        if (!isset($_SESSION['uuid'])) {
            $_SESSION['uuid'] = $__session_user_f['uuid'];
        }

        $stmt = $db->query("SELECT * FROM bans WHERE uuid = :uuid LIMIT 1", [':uuid' => $_SESSION['uuid']]);
        while($ban = $stmt->fetch()) {
            die($twig->load('banned.twig')->render([
                'ip' => $_SERVER['REMOTE_ADDR']
            ]));
        }
    }
} else {
    $_SESSION['loggedIn'] = false;
}
$twig->addGlobal('__SERVER', $_SERVER);
$twig->addGlobal('__SESSION', $_SESSION);
$twig->addGlobal('__CONFIG', $__config);
$twig->addGlobal('_LoggedIn', $_SESSION['loggedIn']);

if (isset($_SESSION['error'])) {
    $twig->addGlobal('__error', $_SESSION['error']);
    unset($_SESSION['error']);
}

$twig->addExtension(new ChenTube\Twig\GetHostURL);
$twig->addExtension(new ChenTube\Twig\time_elapsed_string);
$twig->addExtension(new ChenTube\Twig\FormatVideoDesc);
$twig->addExtension(new ChenTube\Twig\GetUsername($db));
$twig->addExtension(new ChenTube\Twig\GetPFP($db));
$twig->addExtension(new ChenTube\Twig\GetVideoViews($_video_helper));



$router->get('/', function () use ($twig, $db) {
    $latest = $db->query("SELECT v.*, users.username, users.pfp, (
        SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
          AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' ORDER BY published DESC LIMIT 30");
    echo $twig->load('index.twig')->render([
        'page' => [
            'classes' => 'home'
        ],
        'videoslist' => [
            'recommended' => $db->query("SELECT v.*, users.username, users.pfp, (
                SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                  AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' ORDER BY rand() DESC LIMIT 15"),
            'featured' => $db->query("SELECT v.*, users.username, users.pfp, (
                SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                  AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE v.featured = 'y' AND converting = 'n' ORDER BY published DESC LIMIT 3"),
            'latest' => $latest,
        ],
        'channels' => $db->query("SELECT * FROM users WHERE featured = 'y' ORDER BY rand() LIMIT 5"),
        'feed' => [
            'vcount' => $latest->rowCount()
        ]
    ]);
});

$router->get('/results', function () use ($twig, $db) {
    global $_user_helper;
    $search = preg_replace("/[^\\s,a-zA-Z0-9_-]/", "", $_GET['search_query']);
    if (empty($search)) {
        $_GET['search_query'] = $search = 'billygoat891';
    }
    $stmt = $db->query("SELECT * FROM videos WHERE author = :user UNION 
                            SELECT * FROM videos WHERE converting = 'n' AND lower(title) REGEXP :regsearch UNION 
                                SELECT * FROM videos WHERE lower(title) LIKE lower(:search) ORDER BY published DESC LIMIT 22", [
        ':search' => $search,
        ':regsearch' => str_replace("", "", strtolower($search)),
        ':user' => $_user_helper->GetUsernameUUID($search)
    ]);
    echo $twig->load('results.twig')->render([
        'page' => [
            'classes' => 'search-base'
        ],
        'search_query' => $_GET['search_query'],
        'results' => $stmt
    ]);
});

$router->get('/playlist_bar_ajax', function () use ($twig, $db) {
    header("Content-Type: application/json");
    $data = [
        'html' => $twig->load('inc/header_playlist_bar.twig')->render()
    ];
    echo json_encode($data);
});

$router->get('/guide_ajax', function () use ($twig, $db) {
    header("Content-Type: application/json");
    global $_user_helper;
    $html = '';
    if (isset($_GET['action_load_system_feed']) && isset($_GET['feed_name'])) {
        switch ($_GET['feed_name']) {
            case 'chentube':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon youtube" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="From ChenTube">
                    <h2> From ChenTube
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' ORDER BY published DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'trending':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon trending" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Trending">
                    <h2> Trending
                    </h2>
                </div>
            </div>';
                $count = 1;
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE published >= NOW() - INTERVAL 1 WEEK AND converting = 'n' ORDER BY views DESC LIMIT 30");
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'music':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon music" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Music">
                    <h2> Music
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 3 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
        }
    } else if (isset($_GET['action_load_chart_feed']) && isset($_GET['chart_name'])) {
        switch ($_GET['chart_name']) {
            case 'entertainment':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon entertainment" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Entertainment">
                    <h2> Entertainment
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 10 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'sports':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon sports" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Sports">
                    <h2> Sports
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 5 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'comedy':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon comedy" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Comedy">
                    <h2> Comedy
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 9 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'film':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon film" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Film & Animation">
                    <h2> Film & Animation
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 1 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
            case 'gadgets':
                $html = '<div class="feed-header no-metadata">
                <div class="feed-header-thumb">
                    <img class="feed-header-icon gadgets" src="/yts/img/pixel-vfl3z5WfW.gif" alt="">
                </div>
                <div class="feed-header-details context-source-container" data-context-source="Gaming">
                    <h2> Gaming
                    </h2>
                </div>
            </div>';
                $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
                    SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                      AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND category = 7 ORDER BY rand() DESC LIMIT 30");
                $count = 1;
                $vcount = $stmt->rowCount();
                while ($video = $stmt->fetch()) {
                    $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                        'video' => $video,
                        'count' => $count,
                        'vcount' => $vcount
                    ]);
                    $count++;
                }
                if ($count == $vcount) {
                    $count = 1;
                }
                break;
        }
    } else if (isset($_GET['action_load_user_feed']) && isset($_GET['user_id'])) {
        $html = '<div class="feed-header">
        <div class="feed-header-thumb">
            <span class="video-thumb ux-thumb yt-thumb-square-34 "><span class="yt-thumb-clip"><span
                        class="yt-thumb-clip-inner"><img src="/ytd/pfp/' . $_user_helper->GetPFP($_GET['user_id']) . '"
                            alt="Thumbnail" width="34"><span class="vertical-align"></span></span></span></span>
        </div>
        <div class="feed-header-subscribe">
            <div class="yt-subscription-button-hovercard yt-uix-hovercard"><span
                    class="yt-uix-button-subscription-container yt-uix-button-context-light"><button
                        onclick=";window.location.href=this.getAttribute(\'href\');return false;"
                        class="yt-subscription-button yt-subscription-button-js-default yt-uix-button yt-uix-button-subscription"
                        type="button" href="/ServiceLogin" data-subscription-value="qFMzb-4AUf6WAIbl132QKA"
                        data-subscription-feature="guide-header" data-force-position=""
                        data-sessionlink="ei=CI6lmfDn8bQCFa8KIQodnhp4BQ%3D%3D&amp;feature=guide-header" data-position=""
                        data-enable-hovercard="true" data-subscription-type="" role="button"
                        data-subscription-initialized="true"><span class="yt-uix-button-icon-wrapper"><img
                                class="yt-uix-button-icon yt-uix-button-icon-subscribe"
                                src="/yts/img/pixel-vfl3z5WfW.gif"
                                alt=""><span class="yt-uix-button-valign"></span></span><span class="yt-uix-button-content">
                            <span class="subscribe-label">Subscribe</span>
                            <span class="subscribed-label">Subscribed</span>
                            <span class="unsubscribe-label">Unsubscribe</span>
                        </span></button><span class="yt-subscription-button-disabled-mask"></span></span></div>
        </div>
        <div class="feed-header-details">
            <h2>
                ' . $_user_helper->GetUsername($_GET['user_id']) . '
            </h2>
            <p class="metadata">
                <span class="subscriber-count">
                    0 subscriptions
                </span>
                <a href="/channel/' . htmlspecialchars($_GET['user_id']) . '" class="channel-link">View channel<img
                        src="/yts/img/pixel-vfl3z5WfW.gif" class="see-more-arrow" alt=""></a>
            </p>
        </div>
    </div>';
        $stmt = $db->query("SELECT v.*, users.username, users.pfp, (
            SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
              AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND author = :author ORDER BY published DESC LIMIT 30", [':author' => $_GET['user_id']]);
        $vcount = $stmt->rowCount();
        $count = 1;
        while ($video = $stmt->fetch()) {
            $html = $html . $twig->load('inc/video_items/feed_item.twig')->render([
                'video' => $video,
                'count' => $count,
                'vcount' => $vcount
            ]);
        }
    }

    $data = [
        'paging' => null,
        'feed_html' => $html
    ];
    echo json_encode($data, JSON_PRETTY_PRINT);
});

$router->get('/ServiceLogin', function () use ($twig, $db) {
    if ($_SESSION['loggedIn']) {
        header("Location: /yts/img/fail.mp4");
    }
    echo $twig->load('ServiceLogin.twig')->render();
});

$router->post('/ServiceLoginAuth', function () use ($twig, $db) {
    if ($_SESSION['loggedIn']) {
        header("Location: /yts/img/fail.mp4");
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/post/login.php';
});

$router->get('/SignUp', function () use ($twig, $db) {
    if ($_SESSION['loggedIn']) {
        header("Location: /yts/img/fail.mp4");
    }
    echo $twig->load('SignUp.twig')->render();
});

$router->post('/SignUpAuth', function () use ($twig, $db) {
    global $__genid;
    global $__config;
    if ($_SESSION['loggedIn']) {
        header("Location: /yts/img/fail.mp4");
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/post/signup.php';
});

$router->post('/logout', function () use ($twig, $db) {
    unset($_SESSION['token']);
    unset($_SESSION['uuid']);
    $_SESSION['loggedIn'] = false;

    setcookie(
        "token",
        '',
        0,
        '/'
    );

    header("Location: /");
});

// hmm I wonder what this does.
$router->get('/xl', function () use ($twig, $db) {
    echo $twig->load('xl.twig')->render([
        'xl_conf' => [
            'user' => @$_SESSION['token']
        ]
    ]);
});

$router->get('/console_browse', function () use ($twig, $db) {
    header("Content-Type: application/json");
    global $_user_helper;
    $videos = $db->query("SELECT * FROM videos ORDER BY 'published' DESC");

    $data = [
        "content" => [],
        "start" => 1,
        "pretotal" => $videos->rowCount(),
        "total" => $videos->rowCount(),
        "return" => 0
    ];
    foreach ($videos as $video) {
        array_push($data['content'], [
            "dislikes" => 0,
            "time_created" => strtotime($video['published']),
            "likes" => 0,
            "duration" => date("i:s", $video['duration']),
            "displayable_view_count" => 0,
            "user_id" => $video['author'],
            "author" => $_user_helper->GetUsername($video['author']),
            "restricted" => 0,
            "hide_view_count" => false,
            "length_seconds" => 0,
            "published_localized" => date("F D, Y", strtotime($video['published'])),
            "description" => htmlspecialchars($video['description']),
            "format" => "45\/1280x720\/99\/0\/0,22\/1280x720\/9\/0\/115,44\/854x480\/99\/0\/0,35\/854x480\/9\/0\/115,43\/640x360\/99\/0\/0,34\/640x360\/9\/0\/115,18\/640x360\/9\/0\/115,5\/320x240\/7\/0\/0,36\/320x240\/99\/0\/0,17\/176x144\/99\/0\/0",
            "price" => null,
            "expires" => null,
            "video_id" => $video['vid'],
            "image_url" => "/ytd/thumbs/" . htmlspecialchars($video['thumb']),
            "published" => date("F d, Y", strtotime($video['published'])),
            "title" => htmlspecialchars($video['title'])
        ]);
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
});

$router->get('/console_profile_videos', function () use ($twig, $db) {
    header("Content-Type: application/json");
    global $_user_helper;
    $videos = $db->query("SELECT * FROM videos ORDER BY 'published' DESC");

    $data = [
        "content" => [],
        "start" => 1,
        "pretotal" => $videos->rowCount(),
        "total" => $videos->rowCount(),
        "return" => 0
    ];
    foreach ($videos as $video) {
        array_push($data['content'], [
            "dislikes" => 0,
            "time_created" => strtotime($video['published']),
            "likes" => 0,
            "duration" => date("i:s", $video['duration']),
            "displayable_view_count" => 0,
            "user_id" => $video['author'],
            "author" => $_user_helper->GetUsername($video['author']),
            "restricted" => 0,
            "hide_view_count" => false,
            "length_seconds" => 0,
            "published_localized" => date("F D, Y", strtotime($video['published'])),
            "description" => htmlspecialchars($video['description']),
            "format" => "45\/1280x720\/99\/0\/0,22\/1280x720\/9\/0\/115,44\/854x480\/99\/0\/0,35\/854x480\/9\/0\/115,43\/640x360\/99\/0\/0,34\/640x360\/9\/0\/115,18\/640x360\/9\/0\/115,5\/320x240\/7\/0\/0,36\/320x240\/99\/0\/0,17\/176x144\/99\/0\/0",
            "price" => null,
            "expires" => null,
            "video_id" => $video['vid'],
            "image_url" => "/ytd/thumbs/" . htmlspecialchars($video['thumb']),
            "published" => date("F d, Y", strtotime($video['published'])),
            "title" => htmlspecialchars($video['title'])
        ]);
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
});

$router->get('/leanback', function () use ($twig, $db) {
    echo $twig->load('leanback.twig')->render();
});

$router->get('/leanback_oauth', function () use ($twig, $db) {
    echo $twig->load('leanback/leanback_oauth.twig')->render();
});

$router->get('/list_ajax', function () use ($twig, $db) {
    header("Content-Type: application/json");
    $a = [
        "description" => "",
        "video" => [
            [
                "category_id" => 0,
                "description" => "wat",
                "user_id" => "UCkGqCLTYaPmbntXqU1Znzlb7m",
                "session_data" => "feature=playlist",
                "comments" => "4,140",
                "views" => "3,113,522",
                "cc_license" => false,
                "is_hd" => true,
                "length_seconds" => 357,
                "likes" => 46113,
                "added" => "11\/12\/2013",
                "duration" => "5:57",
                "time_created" => 1387558428,
                "privacy" => "public",
                "encrypted_id" => "2ZSzrw9r_p0",
                "is_cc" => false,
                "title" => "the",
                "endscreen_autoplay_session_data" => "feature=autoplay",
                "author" => "DidYouKnowGaming?",
                "keywords" => "",
                "dislikes" => 854,
                "thumbnail" => "https:\/\/web.archive.org\/web\/20200222174219\/https:\/\/i.ytimg.com\/vi\/mQq93ndSWs8\/default.jpg",
                "rating" => 4.0
            ]
        ],
        "author" => "YTOTVgaming",
        "title" => "Favourites",
        "views" => 114
    ];

    echo json_encode($a, JSON_PRETTY_PRINT);
});

$router->get('/watch', function () use ($twig, $db) {
    global $_user_helper;
    global $_video_helper;
    global $__categories_video;
    $_video_q = $db->query("SELECT v.*, users.username, users.pfp, (
        SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
          AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE vid = :vid ORDER BY rand() LIMIT 1", [':vid' => $_GET['v']]);
    $_video = $_video_q->fetch();
    if ($_video_q->rowCount() == 0) {
        echo $twig->load('watch/error.twig')->render([
            'page' => [
                'classes' => 'watch'
            ],
            'msg' => 'This video doesn\'t exist'
        ]);
    } else if ($_video['converting'] == 'y') {
        echo $twig->load('watch/error.twig')->render([
            'page' => [
                'classes' => 'watch'
            ],
            'msg' => 'This video is still converting'
        ]);
    } else {
        $_video_helper->check_view($_video['vid'], $_SERVER['REMOTE_ADDR']);

        $fmt_stream_map = [
            [
                "itag" => "43",
                "url" => (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/ytd/videos/' . $_video['file'])) ? "/ytd/videos_360/" . $_video['file'] : "/ytd/videos/" . $_video['file'],
                "sig" => "21EDBD12A97AC6CFE5B49224A5AD622895FFADEB.913D0D8ADC3EB8203CA6E08F616AC4B63156EC64",
                "fallback_host" => "tc.v14.cache3.c.youtube.com",
                "quality" => "hd720",
                "type" => "video/mp4; codecs=\"avc1.4d002a\""
            ],
            [
                "itag" => "43",
                "url" => (file_exists($_SERVER['DOCUMENT_ROOT'] . '/ytd/videos_360/' . $_video['file'])) ? "/ytd/videos_360/" . $_video['file'] : "/ytd/videos/" . $_video['file'],
                "sig" => "21EDBD12A97AC6CFE5B49224A5AD622895FFADEB.913D0D8ADC3EB8203CA6E08F616AC4B63156EC64",
                "fallback_host" => "tc.v14.cache3.c.youtube.com",
                "quality" => "medium",
                "type" => "video/mp4; codecs=\"avc1.4d002a\""
            ],
        ];

        $count = 1;
        $url_encoded_fmt_stream_map = '';
        foreach ($fmt_stream_map as $stream) {
            if ($count == 0)
                $url_encoded_fmt_stream_map = http_build_query($stream);
            else
                $url_encoded_fmt_stream_map = $url_encoded_fmt_stream_map . "," . http_build_query($stream);
            $count++;
        }

        $_tags = str_replace(" ", "", $_video['tags']);
        $_tags = str_replace(",", "|", $_tags);
        $_tags = preg_replace("/[^a-zA-Z0-9_-|]?$/", "/^$/", $_tags);

        $recommended_videos = $db->query("SELECT v.*, users.username, users.pfp, (
            SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
              AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND
               lower(tags) REGEXP :tags AND converting = 'n' AND vid != :vid ORDER BY tags LIMIT 20", [
            ':tags' => strtolower($_tags),
            ':vid' => $_video['vid'],
        ]);
        $endscreen_videos = $db->query("SELECT v.*, users.username, users.pfp, (
            SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
              AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND
               lower(tags) REGEXP :tags AND converting = 'n' AND vid != :vid ORDER BY tags LIMIT 20", [
            ':tags' => strtolower($_tags),
            ':vid' => $_video['vid'],
        ]);
        if ($recommended_videos->rowCount() == 0) {
            $recommended_videos = $db->query("SELECT v.*, users.username, users.pfp, (
                SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                  AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND vid != :vid ORDER BY tags LIMIT 20", [
                ':vid' => $_video['vid']
            ]);
            $endscreen_videos = $db->query("SELECT v.*, users.username, users.pfp, (
                SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
                  AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE converting = 'n' AND vid != :vid ORDER BY tags LIMIT 20", [
                ':vid' => $_video['vid']
            ]);
        }

        $endscreen = '';
        $count = '0';
        foreach ($endscreen_videos as $video) {
            if ($video['featured'] == 'y') {
                $featured = 1;
            } else {
                $featured = null;
            }
            $data = [
                'title' => htmlspecialchars($video['title']),
                'length_seconds' => urlencode(htmlspecialchars($video['duration'])),
                'author' => urlencode($_user_helper->GetUsername($video['author'])),
                'id' => urlencode(htmlspecialchars($video['vid'])),
                'view_count' => urlencode($_video_helper->get_video_views($video['vid'])),
                'featured' => $featured,
            ];
            $count++;
            if ($count == $endscreen_videos->rowCount()) {
                $last = '';
            } else {
                $last = ',';
            }
            $endscreen = $endscreen . str_replace('&', '\u0026', http_build_query($data) . $last);
        }

        $comments = $db->query("SELECT * FROM comments WHERE vid = :vid ORDER BY posted DESC", [':vid' => $_video['vid']]);

        echo $twig->load('watch/watch.twig')->render([
            'page' => [
                'classes' => 'watch'
            ],
            'video' => $_video,
            'endscreen' => $endscreen,
            'recommended_videos' => $recommended_videos,
            'fmt_stream_map' => $url_encoded_fmt_stream_map,
            'categories' => $__categories_video,
            'comments' => $comments,
            'commentscount' => $comments->rowCount(),
        ]);
    }
});

$router->get('/embed/([^/]+)', function ($vid) use ($twig, $db) {
    global $_user_helper;
    $stmt = $db->query("SELECT * FROM videos ORDER BY rand() LIMIT 12");
    $endscreen = '';
    while ($_video = $stmt->fetch()) {
        $endscreen = $endscreen . 'title=' . htmlspecialchars($_video['title']) . '\u0026length_seconds=' . htmlspecialchars($_video['duration']) . '\u0026author=' . $_user_helper->GetUsername($_video['author']) . '\u0026id=' . htmlspecialchars($_video['vid']) . '\u0026view_count=0,';
    }
    $_video = $db->query("SELECT v.*, users.username, users.pfp, (
        SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
          AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE vid = :vid ORDER BY rand() LIMIT 1", [':vid' => $vid])->fetch();

    echo $twig->load('embed.twig')->render([
        'video' => $_video,
        'endscreen' => $endscreen,
        'recommended_videos' => $db->query("SELECT * FROM videos ORDER BY rand() LIMIT 20")
    ]);
});

$router->get('/get_video_info', function () use ($db) {
    global $_user_helper;
    require_once($_SERVER['DOCUMENT_ROOT'] . '/lib/video/get_video_info.php');
});

$router->get('/get_video_metadata', function () use ($db) {
    global $_user_helper;
    require_once($_SERVER['DOCUMENT_ROOT'] . '/lib/video/get_video_metadata.php');
});

$router->get('/apiplayer', function () use ($db) {
    header("Content-Type: application/x-shockwave-flash");
    echo file_get_contents('/yt/swf/cl.swf');
});

$router->get('/swf/apiplayer.swf', function () use ($db) {
    header("Content-Type: application/x-shockwave-flash");
    echo file_get_contents('/yt/swf/apiplayer.swf');
});

$router->get('/get_video', function () use ($db) {
    header("Content-Type: video/flv");
    echo file_get_contents('ywOVaQhgbAv.flv');
});

$router->post('/comment_servlet', function () use ($db, $twig) {
    header("Content-Type: application/xml");
    global $__genid;
    if (isset($_GET['add_comment']) && $_GET['add_comment'] == '1') {
        $request = (object) [
            "cid" => $__genid->randstr(43),
            "error" => (object) [
                "status" => "OK",
                "msg" => "",
            ]
        ];
        if (!empty($_POST['reply_parent_id'])) {
            $reply = $_POST['reply_parent_id'];
        } else {
            $reply = '';
        }
        $db->query("INSERT INTO comments (cid, comment, author, vid, reply_to) VALUES (:cid, :comment, :author, :vid, :reply_to)", [
            ':cid' => $request->cid,
            ':comment' => $_POST['comment'],
            ':author' => $_SESSION['uuid'],
            ':vid' => $_POST['video_id'],
            ':reply_to' => $reply,
        ]);

        echo '<root><str_code><![CDATA[OK]]></str_code><html_content><![CDATA[' . $twig->load('inc/watch/comment.twig')->render([
            'comment' => [
                'cid' => $request->cid,
                'comment' => $_POST['comment'],
                'author' => $_SESSION['uuid'],
                'posted' => date('Y-m-d h:i:s')
            ]
        ]) . ']]></html_content><return_code><![CDATA[0]]></return_code></root>';
    } else if (isset($_GET['get_comment_parent']) && $_GET['get_comment_parent'] == '1') {
        $comment = $db->query("SELECT * FROM comments WHERE cid= :cid ORDER BY posted DESC LIMIT 1", [
            ':cid' => $db->query("SELECT * FROM comments WHERE cid = :cid ORDER BY posted DESC LIMIT 1", [':cid' => $_POST['comment_id']])->fetch()['reply_to']
        ])->fetch();

        echo '<root><str_code><![CDATA[OK]]></str_code><html_content><![CDATA[' . $twig->load('inc/watch/comment.twig')->render(['comment' => $comment]) . ']]></html_content><return_code><![CDATA[0]]></return_code></root>';
    }
});

$router->get('/blank', function () use ($db) {
});

$router->get('/token_ajax', function () use ($db) {
    header("Content-Type: text/plain");
    $data = [
        'status' => '200',
        'html_ajax_token' => '1',
        'watch_actions_ajax_token' => '1',
        'addto_ajax_token' => '1'
    ];

    die(http_build_query($data));
});

$router->get('/share_ajax', function () use ($db, $twig) {
    header("Content-Type: application/json");
    if (isset($_GET['action_get_share_box']) && $_GET['action_get_share_box'] == '1') {
        $template = $twig->load('json_inc/share_ajax.twig')->render();
        $data = [
            "url_short" => GetHostURL() . "/watch?v=" . htmlspecialchars($_GET['video_id']),
            "share_html" => $template,
            "lang" => "en",
            "url_long" => GetHostURL() . "/watch?v=" . htmlspecialchars($_GET['video_id']),
        ];

        die(json_encode($data));
    } else if (isset($_GET['action_get_embed']) && $_GET['action_get_embed'] == '1') {
        $template = $twig->load('json_inc/share_ajax_embed.twig')->render();
        $data = [
            "embed_html" => $template,
            "vid" => htmlspecialchars($_GET['video_id']),
            "legacy_code" => "why tf are you using flash in 2022",
            "iframe_code" => "<iframe width=\"__width__\" height=\"__height__\" src=\"__url__\" frameborder=\"0\" allowfullscreen></iframe>",
            "iframe_url" => GetHostURL() . "/embed/" . htmlspecialchars($_GET['video_id']),
            "legacy_url" => ""
        ];

        die(json_encode($data));
    }
});

$router->get('/help/api/topic/([^/]+)/([^/]+)', function ($vid, $size) use ($db) {
    header("text/javascript");
    echo $_GET['callback'] . '({
        "lang" =>  "en",
        "topic" =>  {
         "topic_id" =>  "1699306",
         "name" =>  "Homepage, youtube.com",
         "topics" =>  [
         ],
         "answers" =>  [
          {
           "answer_id" =>  "0",
           "name" =>  "Gangster",
           "question" =>  "",
           "inproduct_target" =>  null
          }
         ],
         "parent_topic" =>  {
          "topic_id" =>  "1699305",
          "name" =>  "Helpie"
         }
        }
       }
       );';
});

$router->get('/help/api/answer/([^/]+)/([^/]+)', function ($vid, $size) use ($db) {
    header("text/javascript");
    echo $_GET['callback'] . '({
        "lang" =>  "en",
        "answer" =>  {
         "answer_id" =>  "1186999",
         "name" =>  "Gangster",
         "question" =>  "Gangster",
         "answer" =>  "\u003ciframe src=\"/embed/XryApJ1q2NL\" width=\"100%\" height=\"225\"\u003e\u003c/iframe\u003e"
        }
       }
       );';
});

$router->get('/vi/([^/]+)/([^/]+)', function ($vid, $size) use ($db) {
    $stmt = $db->query("SELECT thumb FROM videos WHERE vid = :vid LIMIT 1", [
        ':vid' => $vid
    ]);
    header('Location: /ytd/thumbs/' . $stmt->fetch()['thumb']);
});


$router->get('/channel/([^/]+)', function ($user) use ($twig, $db) {
    global $_user_helper;
    $featured_video = $db->query("SELECT v.*, users.username, users.pfp, (
        SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
          AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE author = :user AND converting = 'n' ORDER BY published DESC LIMIT 1", [':user' => $user]);
    if ($featured_video->rowCount() == 0) {
        $featured_video = null;
    } else {
        $featured_video = $featured_video->fetch();
    }


    echo $twig->load('channel/3/featured.twig')->render([
        'page' => [
            'classes' => 'branded-page channel'
        ],
        'user' => $db->query("SELECT * FROM users WHERE uuid = :uuid", [':uuid' => $user])->fetch(),
        'uploaded_videos' => $db->query("SELECT v.*, users.username, users.pfp, (
            SELECT count(1) FROM views AS viw WHERE viw.vid=v.vid)
              AS views FROM videos AS v INNER JOIN users ON v.author=users.uuid WHERE author = :author AND converting = 'n' ORDER BY published DESC LIMIT 10", [':author' => $user]),
        'featured_video' => $featured_video,
        'uploaded_videos_count' => $_user_helper->uploaded_vcount($user)
    ]);
});


$router->get('/html5', function () use ($twig, $db) {
    $year = date('Y');
    echo "The thing you should be using in $year.";
});

$router->get('/my_videos', function () use ($twig, $db) {
    echo $twig->load('my_videos.twig')->render();
});

$router->get('/my_videos_upload', function () use ($twig, $db) {
    global $__categories_video;
    if (!$_SESSION['loggedIn']) {
        header("Location: /ServiceLogin");
    }
    echo $twig->load('my_videos_upload.twig')->render([
        'categories' => $__categories_video,
    ]);
});

$router->post('/my_videos_upload_post', function () use ($twig, $db) {
    global $__genid;
    global $_user_helper;
    global $__categories_video;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/post/upload.php';
});

$router->get('/gen_204', function () use ($twig, $db) {
    header("HTTP/1.1 204 No Content");
});

$router->get('/phpmyadmin', function () use ($twig, $db) {
    header("Content-Type: video/mp4");
    echo file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/yts/img/hax.mp4");
});

$router->set404('/', function () use ($twig) {
    header('HTTP/1.1 404 Not Found');
    echo $twig->load('404.twig')->render();
});

$router->set404('/ytd/thumbs(/.*)?', function () use ($twig) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: image/jpg');
    echo file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/yts/img/default_thumb.jpg');
});

$router->set404('/ytd/pfp(/.*)?', function () use ($twig) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: image/jpg');
    echo file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/yts/img/no_videos_140-vfl5AhOQY.png');
});

$router->run();
