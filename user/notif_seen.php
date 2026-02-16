<?php
session_start();
require_once "../config/database.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "USER") {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "Unauthorized"]);
  exit();
}

$user_id = (int)$_SESSION["user_id"];

// Upsert: insert if not exists, else update
$stmt = $conn->prepare("
  INSERT INTO user_notif_seen (user_id, last_seen_at)
  VALUES (?, NOW())
  ON DUPLICATE KEY UPDATE last_seen_at = NOW()
");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
  echo json_encode(["ok" => true]);
} else {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "DB error"]);
}
