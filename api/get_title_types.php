<?php
require_once "../config/database.php";

$doc = trim($_GET["doc"] ?? "");
header("Content-Type: application/json");

if ($doc === "") {
  echo json_encode([]);
  exit();
}

$stmt = $conn->prepare("
  SELECT DISTINCT title_type
  FROM requirements_master
  WHERE document_type = ?
  ORDER BY title_type ASC
");
$stmt->bind_param("s", $doc);
$stmt->execute();

$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) $out[] = $row;

echo json_encode($out);
