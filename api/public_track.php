<?php
require_once "../config/database.php";
header("Content-Type: application/json");

$ref = strtoupper(trim($_GET["ref"] ?? ""));
if ($ref === "") {
  echo json_encode(["ok"=>false, "error"=>"Missing reference number."]);
  exit();
}

// Only public fields (no user info)
$stmt = $conn->prepare("
  SELECT id, reference_no, document_type, status
  FROM requests
  WHERE reference_no = ?
  LIMIT 1
");
$stmt->bind_param("s", $ref);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) {
  echo json_encode(["ok"=>false, "error"=>"Reference number not found."]);
  exit();
}

$rid = (int)$req["id"];

$logStmt = $conn->prepare("
  SELECT message, created_at
  FROM request_logs
  WHERE request_id = ?
  ORDER BY created_at ASC
");
$logStmt->bind_param("i", $rid);
$logStmt->execute();
$logs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Format dates for display
foreach ($logs as &$l){
  if (!empty($l["created_at"])) {
    $l["created_at"] = date("m/d/y, g:i A", strtotime($l["created_at"]));
  }
}

echo json_encode([
  "ok" => true,
  "request" => $req,
  "logs" => $logs
]);
