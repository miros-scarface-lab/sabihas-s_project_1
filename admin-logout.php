<?php

session_start();

/* Remove admin session */
unset($_SESSION['admin_username']);

if (empty($_SESSION['user_id'])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/* Go to Admin Login */
header("Location: admin-login.php");
exit();

?>