<?php
    $request = (object) [
        "username" => $_POST['Username'],
        "email" => $_POST['Email'],
        "password" => password_hash($_POST['Passwd'], PASSWORD_DEFAULT),
        "uuid" => $__genid->GenUUID(),
        "token" => bin2hex(random_bytes(32)),
        'ip' => $_SERVER['REMOTE_ADDR'],
        "error" => (object) [
            "status" => "OK",
            "message" => "",
        ]
    ];
    // Validate hCAPTCHA
    if(isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) { 
 
        // Verify the hCAPTCHA API response 
        $verifyResponse = file_get_contents('https://hcaptcha.com/siteverify?secret='.$__config['hcaptcha']['secret'].'&response='.$_POST['h-captcha-response']); 
         
        // Decode JSON data of API response 
        $responseData = json_decode($verifyResponse); 
         
        // If the hCAPTCHA API response is valid 
        if($responseData->success){
            // thx: https://gist.github.com/hnq90/316f08047a3bf348b823
            function checkEmoji($str) {
                $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
                preg_match($regexEmoticons, $str, $matches_emo);
                if (!empty($matches_emo[0])) {
                    return false;
                }
                
                // Match Miscellaneous Symbols and Pictographs
                $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
                preg_match($regexSymbols, $str, $matches_sym);
                if (!empty($matches_sym[0])) {
                    return false;
                }

                // Match Transport And Map Symbols
                $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
                preg_match($regexTransport, $str, $matches_trans);
                if (!empty($matches_trans[0])) {
                    return false;
                }
            
                // Match Miscellaneous Symbols
                $regexMisc = '/[\x{2600}-\x{26FF}]/u';
                preg_match($regexMisc, $str, $matches_misc);
                if (!empty($matches_misc[0])) {
                    return false;
                }

                // Match Dingbats
                $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
                preg_match($regexDingbats, $str, $matches_bats);
                if (!empty($matches_bats[0])) {
                    return false;
                }

                return true;
            }
            switch (true) {
                /* case !isset($_POST['TermsOfService']):
                    $request->error->message = "In order to use our services, you must agree to ChenTube's Terms of Service."; $request->error->status = "";
                    break; */
                case strlen($request->username) < 3:
                    $request->error->message = "Your username must be at least 3 characters long."; $request->error->status = "";
                    break;
                case strlen($request->username) > 21:
                    $request->error->message = "Your username must be shorter than 20 characters."; $request->error->status = "";
                    break;
                case !checkEmoji($request->username):
                    $request->error->message = "Your username cannot contain special charactors."; $request->error->status = "";
                    break;  
                case !filter_var($request->email, FILTER_VALIDATE_EMAIL):
                    $request->error->message = "Your email is invalid."; $request->error->status = "";
                    break;
                case strlen($request->password) < 8:
                    $request->error->message = "Your password must be at least 8 characters long."; $request->error->status = "";
                    break;
                case !preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $request->password):
                    $request->error->message = "Your password must include numbers and letters."; $request->error->status = "";
                    break;    
                case $_POST['Passwd'] !== $_POST['PasswdAgain']:
                    $request->error->message = "Your passwords don't match."; $request->error->status = "";
                    break;
            }

            if(preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $request->username)) {
                $request->error->message = "Your username cannot contain any special characters."; $request->error->status = "";
            }

            $stmt = $db->query("SELECT username FROM users WHERE username = lower(:username)", [
                ':username' => $request->username,
            ]);
            if($stmt->rowCount() > 1) 
                { $request->error->message = "There's already a user with that same username."; $request->error->status = ""; }

            $stmt = $db->query("SELECT email FROM users WHERE email = :email", [
                ':email' => $request->email,
            ]);
            if($stmt->rowCount() > 0) 
                { $request->error->message = "Someone has already registered with that email."; $request->error->status = ""; }

            $stmt = $db->query("SELECT ip FROM users WHERE ip = :ip", [
                ':ip' => $request->ip,
            ]);

            //if($stmt->rowCount() > 3) 
            //    { $request->error->message = "You cannot make anymore alts."; $request->error->status = ""; }

            if($request->error->status == "OK") {
                $db->query("INSERT INTO users (username, email, password, uuid, token, ip) VALUES (:username, :email, :password, :uuid, :token, :ip)", [
                    ':username' => $request->username,
                    ':email' => $request->email,
                    ':password' => $request->password,
                    ':uuid' => $request->uuid,
                    ':token' => $request->token,
                    ':ip' => $request->ip,
                ]);

                $_SESSION['token'] = $request->token;
                $_SESSION['uuid'] = $request->uuid;
                $_SESSION['loggedIn'] = true;
                setcookie(
                    "token",
                    $_SESSION['token'],
                    time() + (10 * 365 * 24 * 60 * 60),
                    '/'
                    );
                header("Location: /");
            } else {
                $_SESSION['error'] = $request->error->message;
                header("Location: /SignUp");
            }
        } else { 
            $request->error->message = 'Robot verification failed, please try again.'; $request->error->status = "";
            $_SESSION['error'] = $request->error->message;
            header("Location: /SignUp");
        } 
    } else { 
        $request->error->message = 'Please check the hCAPTCHA checkbox.'; $request->error->status = "";
        // janky fix
        $_SESSION['error'] = $request->error->message;
        header("Location: /SignUp");
    }
?>