<?php
/**
 * SweetAlert2 shared include.
 * Add this inside the <head> tag of every HTML-rendering PHP page.
 *
 * Usage:
 *   <?php include __DIR__ . "/../includes/swal_header.php"; ?>
 */
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/swal-helpers.js"></script>
<?php if (!empty($_SESSION["swal_flash"])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon:  <?= json_encode($_SESSION["swal_flash"]["icon"]) ?>,
            title: <?= json_encode($_SESSION["swal_flash"]["title"]) ?>,
            text:  <?= json_encode($_SESSION["swal_flash"]["text"]) ?>,
            <?php if ($_SESSION["swal_flash"]["icon"] === "success"): ?>
            timer: 2000,
            showConfirmButton: false
            <?php endif; ?>
        });
    });
</script>
<?php unset($_SESSION["swal_flash"]); endif; ?>
