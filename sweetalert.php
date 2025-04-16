<?php if (isset($_SESSION['alert'])): ?>
  <script>
    Swal.fire({
      title: '<?= $_SESSION['alert']['title'] ?>',
      text: '<?= $_SESSION['alert']['text'] ?>',
      icon: '<?= $_SESSION['alert']['icon'] ?>',
      timer: 2000,
      showConfirmButton: false
    });
  </script>
  <?php unset($_SESSION['alert']); ?>
<?php endif; ?>
