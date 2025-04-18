<?php
// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?> 