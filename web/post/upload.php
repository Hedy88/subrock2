<?php 
if($_SESSION['loggedIn']) {
    $request = (object) [
        "targetdir" => $_SERVER['DOCUMENT_ROOT'] . "/ytd/videos/",
        "tempdir" => $_SERVER['DOCUMENT_ROOT'] . "/ytd/temp/". $__genid->randstr(26) . "/",
        "thumbdir" =>  $_SERVER['DOCUMENT_ROOT'] . "/ytd/thumbs/",
        "vfile" => $_FILES["video_file"],
        "vext" => strtolower(pathinfo($_FILES["video_file"]["name"], PATHINFO_EXTENSION)),
        "vtitle" => $_POST['title'],
        "vdesc" => $_POST['description'],
        "vtags" => $_POST['tags'],
        "vcategory" => $_POST['category'],
        "v_id" => $__genid->GenVidID(),
        "filename" => $__genid->randstr(32),
        "error" => (object) [
            "status" => "OK",
            "msg" => ""
        ],
    ];

    switch(true) {
        case $_user_helper->upload_cooldown($_SESSION['uuid']):
            $request->error->msg = "You must wait 5 minutes before uploading"; $request->error->status = "";
            break;
        case $request->vfile["error"] == 4:
            $request->error->msg = "You must select a file"; $request->error->status = "";
            break;
        case empty(trim($request->vtitle)):
            $request->error->msg = "You must specify a title"; $request->error->status = "";
            break;
        case !isset($__categories_video[$request->vcategory]):
            $request->error->msg = "Invalid category"; $request->error->status = "";
            break;
        case 
        $request->vext != "mp4" &&
        $request->vext != "mov" &&
        $request->vext != "wmv" &&
        $request->vext != "avi" &&
        $request->vext != "flv" &&
        $request->vext != "mkv" &&
        $request->vext != "webm":
            $request->error->msg = "You must select a valid video file"; $request->error->status = "";
            break;
    }

    if($request->error->status == "OK") {
        header('HTTP/1.1 200');
        if (!file_exists($request->tempdir)) {
            mkdir($request->tempdir);
        }
        if(move_uploaded_file($request->vfile['tmp_name'], $request->tempdir.$request->filename.".".$request->vext)) {
            $vthumb = $request->filename.".jpg";
            $vfile = $request->filename.".webm";
            $db->query("INSERT INTO videos (vid, file, title, description, tags, author, thumb, duration, category) 
            VALUES (:v_id, :file, :title, :desc, :tags, :author, :thumb, '0', :category)", [
                ":v_id" => $request->v_id,
                ":file" => $vfile,
                ":title" => $request->vtitle,
                ":desc" => $request->vdesc,
                ":tags" => $request->vtags,
                ":author" => $_SESSION['uuid'],
                ":thumb" => $vthumb,
                ":category" => $request->vcategory,
            ]);
            system(sprintf('php lib/scripts/upload_processingworker.php "%s" "%s" "%s" "%s" > %s 2>&1 &', $request->filename, $request->tempdir, $request->vext, $request->v_id, $_SERVER['DOCUMENT_ROOT'] . '/ytd/temp/' . '' . '.log'));
            
            
            $db->query("UPDATE users SET upload_cooldown = CURRENT_TIMESTAMP WHERE uuid = :uuid", [
                ":uuid" => $_SESSION['uuid']
            ]);
            
            
            echo "
            <script>
                window.location = '/my_videos';
            </script>

            ";
        }
    } else {
        header('HTTP/1.1 400');
        echo "<script>alert('".htmlspecialchars($request->error->msg)."')</script>";
    }
} else {
    
    echo "Login first, script kiddie";

} ?>