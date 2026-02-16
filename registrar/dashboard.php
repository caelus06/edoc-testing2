<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "REGISTRAR") {
  header("Location: ../auth/auth.php");
  exit();
}
?>
<h1>Registrar Dashboard</h1>
<a href="../auth/logout.php">Logout</a>
