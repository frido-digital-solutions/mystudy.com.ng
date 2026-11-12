    

    <!-- Bootstrap 5 JS Bundle with Popper (Local) -->
    <script src="<?php echo SITE_URL; ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
      (function(){
        if (typeof window.bootstrap === 'undefined') {
          var s=document.createElement('script');
          s.src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
          document.body.appendChild(s);
        }
      })();
    </script>
    
    <!-- Custom Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });

            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (el) { return new bootstrap.Popover(el); });

            // Auto-dismiss alerts after 5 seconds
            document.querySelectorAll('.alert').forEach(function(alert) {
                setTimeout(function() { new bootstrap.Alert(alert).close(); }, 5000);
            });
        });
    </script>
    
    <?php 
    // Output any additional scripts that might be set in the page
    if (isset($page_scripts)) {
        echo $page_scripts;
    }
    ?>
</body>
</html>
<?php 
// End output buffering and flush the buffer
ob_end_flush(); 
?>
