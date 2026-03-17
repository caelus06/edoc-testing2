<?php
require_once __DIR__ . "/../includes/helpers.php";
require_role(ROLE_USER);
?>
<form method="POST" action="upload_requirements.php">
  <?= csrf_field() ?>
  <h2>Upload Requirements</h2>
  <select name="document_type" required>
    <option value="">-- Select Document --</option>
    <option>TRANSCRIPT OF RECORDS</option>
    <option>DIPLOMA</option>
  </select>
  <button type="submit">NEXT</button>
</form>
