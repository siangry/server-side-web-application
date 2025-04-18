<?php
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "assignment_v5";
    $conn = mysqli_connect($host, $user, $password, $dbname);
    if (mysqli_connect_errno()) {
        die("Connection failed: " . mysqli_connect_error());
    }
?>