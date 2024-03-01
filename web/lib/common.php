<?php
namespace ChenTube;

use DateTime;
use Exception;
use Throwable;

function exception_handler(Throwable $exception) {
  header("HTTP/1.1 500 Internal Server Error");
  echo "<h1>Internal Server Error</h1><b>Screenshot and report this screen to the admins</b><hr>".$exception;
}

set_exception_handler('ChenTube\exception_handler');

if(file_exists('conf/config.php')) {
  require_once 'conf/config.php';
} else {
  throw new Exception("You don't have a config file set up please clone the config.sample.php file in conf/");
}

foreach(glob('lib/*.php') as $file) {
  require_once $file;
}

$db = new MySQL($__config['mysql']['host'], $__config['mysql']['db'], $__config['mysql']['username'], $__config['mysql']['password']);

$_user_helper = new user_helper($db);
$_video_helper = new video_helper($db);

$__genid = new GenID;

session_start();

class GenID {
  function GenUUID($length = 24) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';
    $charactersLength = strlen($characters);
    $randomString = 'UC';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }
  function GenVidID($length = 11) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }
  function randstr($len, $charset = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-"){
    return substr(str_shuffle($charset),0,$len);
  }
}

function isMobileDevice() {
  return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo
|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i"
, $_SERVER["HTTP_USER_AGENT"]);
}

function time_elapsed_string($datetime, $full = false) {
  $now = new DateTime;
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);

  $diff->w = floor($diff->d / 7);
  $diff->d -= $diff->w * 7;

  $string = array(
      'y' => 'year',
      'm' => 'month',
      'w' => 'week',
      'd' => 'day',
      'h' => 'hour',
      'i' => 'minute',
      's' => 'second',
  );
  foreach ($string as $k => &$v) {
      if ($diff->$k) {
          $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
      } else {
          unset($string[$k]);
      }
  }

  if (!$full) $string = array_slice($string, 0, 1);
  return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function GetHostURL() {
  return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
}

$__categories_video = [
    "1" => "Film & Animation",
    "2" => "Autos & Vehicles",
    "3" => "Music",
    "4" => "Pets & Animals",
    "5" => "Sports",
    "6" => "Travel & Events",
    "7" => "Gaming",
    "8" => "People & Blogs",
    "9" => "Comedy",
    "10" => "Entertainment",
    "11" => "News & Politics",
    "12" => "Howto & Style",
    "13" => "Education",
    "14" => "Science & Technology",
    "15" => "Nonprofits & Activism",
];

?>