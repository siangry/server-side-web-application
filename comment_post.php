<?php
session_start();
require 'database.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$post_id = intval($_REQUEST['post_id']);
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // add comment
    $comment = trim($_POST['comment']);
    $stmt = $conn->prepare("INSERT INTO post_comment(post_id,user_id,comment) VALUES(?,?,?)");
    $stmt->bind_param("iis",$post_id,$user_id,$comment);
    $stmt->execute();
    $stmt->close();
}

// fetch comments
$res = $conn->query("
    SELECT c.comment, c.created_at, u.username
    FROM post_comment c
    JOIN user u ON c.user_id=u.user_id
    WHERE c.post_id=$post_id
    ORDER BY c.created_at DESC
");
$comments = [];
while ($r = $res->fetch_assoc()) {
    $comments[] = $r;
}

header('Content-Type: application/json');
echo json_encode($comments);
