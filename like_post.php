<?php
session_start();
require 'database.php';
if(!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
$post_id = intval($_POST['post_id']);
$user_id = $_SESSION['user_id'];

// check existing
$stmt = $conn->prepare("SELECT 1 FROM post_like WHERE post_id=? AND user_id=?");
$stmt->bind_param("ii",$post_id,$user_id);
$stmt->execute(); $stmt->store_result();

if($stmt->num_rows>0){
    // unlike
    $del = $conn->prepare("DELETE FROM post_like WHERE post_id=? AND user_id=?");
    $del->bind_param("ii",$post_id,$user_id);
    $del->execute(); $del->close();
    $action = "unliked";
} else {
    // like
    $ins = $conn->prepare("INSERT INTO post_like(post_id,user_id) VALUES(?,?)");
    $ins->bind_param("ii",$post_id,$user_id);
    $ins->execute(); $ins->close();
    $action = "liked";
}
$stmt->close();

// new count
$res = $conn->query("SELECT COUNT(*) AS cnt FROM post_like WHERE post_id=$post_id");
$row = $res->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(["action"=>$action,"count"=>$row['cnt']]);
