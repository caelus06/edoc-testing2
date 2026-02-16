<?php
require_once "../config/database.php";
header("Content-Type: application/json");

$doc = strtoupper(trim($_GET["doc"] ?? ""));
$title = strtoupper(trim($_GET["title"] ?? ""));

if ($doc === "" || $title === "") {
  echo json_encode(["ok"=>false, "error"=>"Missing doc/title."]);
  exit();
}

$stmt = $conn->prepare("
  SELECT req_name
  FROM requirements_master
  WHERE UPPER(document_type) = ? AND UPPER(title_type) = ?
  ORDER BY id ASC
");
$stmt->bind_param("ss", $doc, $title);
$stmt->execute();
$reqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(["ok"=>true, "requirements"=>$reqs]);
