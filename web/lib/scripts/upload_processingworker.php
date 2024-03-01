<?php
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }

    }

    return rmdir($dir);
}
require_once $_SERVER['DOCUMENT_ROOT'] . 'conf/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . 'lib/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . 'vendor/autoload.php';
$request = (object) [
    "tempdir" => $argv[2],
    "filename" => $argv[1],
    "vext" => $argv[3],
    "vid" => $argv[4],
    "thumbdir" => "ytd/thumbs/",
    "targetdir" => "ytd/videos/",
    "targetdir_360" => "ytd/videos_360/",
];
$config = [
	'timeout'          => 3600, // The timeout for the underlying process
	'ffmpeg.threads'   => 2,   // The number of threads that FFmpeg should use
	'ffmpeg.binaries'  => ($__config['ffmpeg']['ffmpeg_bin']),
	'ffprobe.binaries' => ($__config['ffmpeg']['ffprobe_bin']),
];

try {
    $ffmpeg = FFMpeg\FFMpeg::create($config);
    $ffprobe = FFMpeg\FFProbe::create($config);
    $duration = round($ffprobe
        ->streams($request->tempdir.$request->filename.".".$request->vext)
        ->videos()                   
        ->first()                  
        ->get('duration'));
        
    $video = $ffmpeg->open($request->tempdir.$request->filename.".".$request->vext);
    $video_360 = $ffmpeg->open($request->tempdir.$request->filename.".".$request->vext);

    $video_360
        ->filters()
        ->resize(new FFMpeg\Coordinate\Dimension(640, 360), FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, true)
        ->custom('format=yuv420p');
    $format = new FFMpeg\Format\Video\WebM();

    $format->on('progress', function ($video, $format, $percentage) {
        echo "$percentage % transcoded \n";
    });

    $format
        ->setKiloBitrate(1000)
        ->setAudioChannels(2)
        ->setAudioKiloBitrate(256);

    $video_360->save($format, $request->targetdir_360.$request->filename.'.webm');

    exec(
        $__config['ffmpeg']['ffmpeg_bin'].
        " -i ".
        $request->tempdir.$request->filename.".".$request->vext.
        " -vf \"select=eq(n\\,".rand(0, $duration)."),scale=960:720:force_original_aspect_ratio=decrease,pad=960:720:-1:-1:color=black\" -vframes 1 ".
        $request->thumbdir.$request->filename.".jpg"
    );

    $db->query("UPDATE videos SET duration = :duration, converting = 'n' WHERE vid = :vid", [
        ":vid" => $request->vid,
        ":duration" => $duration
    ]);
    
    $video
        ->filters()
        ->resize(new FFMpeg\Coordinate\Dimension(1280, 720), FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, true)
        ->custom('format=yuv420p');
        
    $video->save($format, $request->targetdir.$request->filename.'.webm');
    deleteDirectory($request->tempdir);

} catch (Exception $e) {
	echo "Something went wrong!: ". $e->getMessage();
    deleteDirectory($request->tempdir);
}

?>