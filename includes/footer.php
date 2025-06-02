<footer class="bg-dark text-light mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="fas fa-heart"></i> Antenatal Care</h5>
                <p>Supporting expectant mothers through their pregnancy journey with personalized care and information.
                </p>
            </div>
            <div class="col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php" class="text-light">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php" class="text-light">Dashboard</a></li>
                        <li><a href="content.php" class="text-light">Content</a></li>
                    <?php else: ?>
                        <li><a href="register.php" class="text-light">Register</a></li>
                        <li><a href="login.php" class="text-light">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr>
        <div class="text-center">
            <p>&copy; 2025 Antenatal Care Platform. All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>

</html>