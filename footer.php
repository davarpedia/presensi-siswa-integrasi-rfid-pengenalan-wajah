<!-- Footer -->
<footer class="sticky-footer bg-white">
  <div class="container my-auto">
    <div class="copyright text-center my-auto">
      <span>Copyright &copy; David Ardianto 2025</span>
    </div>
  </div>
</footer>
<!-- End of Footer -->
</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
  <i class="fas fa-angle-up"></i>
</a>

<!-- Bootstrap core JavaScript-->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Core plugin JavaScript-->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="js/sb-admin-2.min.js"></script>

<!-- Page level plugins -->
<script src="vendor/chart.js/Chart.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Tanggal dan Waktu Realtime -->
<script src="js/waktu-realtime.js"></script>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SweetAlert Session Alert -->
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

</body>
</html>
