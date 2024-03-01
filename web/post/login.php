<?php
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $request = (object) [
            "username" => $_POST['Username'],
            "password" => $_POST['Passwd'],
            "error" => (object) [
                "status" => "OK",
                "message" => "",
            ]
        ];

        $stmt = $db->query("SELECT token, uuid, password FROM users WHERE username = :username LIMIT 1", [
            ':username' => $request->username,
        ]);
        $row = $stmt->fetch();

        if($stmt->rowCount() == 0 || 
        !password_verify($request->password, $row['password'])) {
            $request->error->message = "Your username or password is incorrect"; $request->error->status = "";
        }

        if($request->error->status == "OK") {
            $_SESSION['token'] = $row['token'];
            $_SESSION['uuid'] = $row['uuid'];
            $_SESSION['loggedIn'] = true;
            if(isset($_POST['PersistentCookie'])) {
                setcookie(
                    "token",
                    $_SESSION['token'],
                    time() + (10 * 365 * 24 * 60 * 60),
                    '/'
                  );
            }

           // $stmt = $db->query("UPDATE users SET lastlogin = current_timestamp() WHERE username = :username", [
             //   ':username' => $request->username,
            //]);

            header("Location: /");
        } else {
            $_SESSION['error'] = $request->error->message;
            header("Location: /SignUp");
        }
    }
?>