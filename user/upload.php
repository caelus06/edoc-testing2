<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) header("Location: ../auth/auth.php");
?>
<form method="POST" action="upload_requirements.php">
  <h2>Upload Requirements</h2>
  <select name="document_type" required>
    <option value="">-- Select Document --</option>
    <option>TRANSCRIPT OF RECORDS</option>
    <option>DIPLOMA</option>
  </select>
  <button type="submit">NEXT</button>
</form>
