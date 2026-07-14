    </main>
    <footer class="app-footer">
        <div class="container">
            <div class="row align-items-center gy-3">
                <div class="col-md-6">
                    <h5>Anti-Counterfeit System</h5>
                    <p>Protecting brands and consumers with secure product verification.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> Anti-Counterfeit System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    <script>window.APP_LOGOUT_URL = "<?php echo e(page_url('logout')); ?>";</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo e(asset_url('assets/js/app.js')); ?>"></script>
</body>
</html>
