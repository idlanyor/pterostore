<?php
session_start();
require_once 'config/database.php';
require_once 'config/pterodactyl.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antidonasi Store - Panel Pterodactyl Private</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-server me-2"></i>Antidonasi Store
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#products">Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Keunggulan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Kontak</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="user/dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="user/profile.php">Profil</a></li>
                                <li><a class="dropdown-item" href="user/orders.php">Riwayat Order</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Panel Pterodactyl Private
                    </h1>
                    <p class="lead text-white mb-4">
                        Nikmati performa server terbaik dengan uptime 99%, DDos Protection, dan processor Epyc Genoa & ECC DDR5.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#products" class="btn btn-primary btn-lg">
                            <i class="fas fa-rocket me-2"></i>Lihat Produk
                        </a>
                        <?php if(!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-server hero-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Pilihan Paket Server</h2>
                <p class="lead">Pilih paket sesuai kebutuhan Anda</p>
            </div>

            <!-- NodeJS VIP -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">
                        <i class="fab fa-node-js text-success me-2"></i>NodeJS VIP (A1-A6)
                    </h3>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-success text-white text-center">
                            <h5 class="mb-0">A1 - NodeJS Kroco</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-success">IDR 5.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>3GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>100% CPU</li>
                            </ul>
                            <a href="order.php?product=A1" class="btn btn-success">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-warning text-white text-center">
                            <h5 class="mb-0">A2 - NodeJS Karbit</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-warning">IDR 7.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>5GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>150% CPU</li>
                            </ul>
                            <a href="order.php?product=A2" class="btn btn-warning">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-orange text-white text-center">
                            <h5 class="mb-0">A3 - NodeJS Standar</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-orange">IDR 10.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>7GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>200% CPU</li>
                            </ul>
                            <a href="order.php?product=A3" class="btn btn-orange">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-danger text-white text-center">
                            <h5 class="mb-0">A4 - NodeJS Sepuh</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-danger">IDR 12.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>11GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>250% CPU</li>
                            </ul>
                            <a href="order.php?product=A4" class="btn btn-danger">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-purple text-white text-center">
                            <h5 class="mb-0">A5 - NodeJS Suhu</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-purple">IDR 15.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>13GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>300% CPU</li>
                            </ul>
                            <a href="order.php?product=A5" class="btn btn-purple">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-diamond text-white text-center">
                            <h5 class="mb-0">A6 - NodeJS Pro Max</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-diamond">IDR 20.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>16GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>400% CPU</li>
                            </ul>
                            <a href="order.php?product=A6" class="btn btn-diamond">Pilih Paket</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VPS -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-server text-primary me-2"></i>VPS (B1-B6)
                    </h3>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-success text-white text-center">
                            <h5 class="mb-0">B1 - VPS Kroco</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-success">IDR 7.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>3GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>100% CPU</li>
                            </ul>
                            <a href="order.php?product=B1" class="btn btn-success">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-warning text-white text-center">
                            <h5 class="mb-0">B2 - VPS Karbit</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-warning">IDR 10.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>5GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>150% CPU</li>
                            </ul>
                            <a href="order.php?product=B2" class="btn btn-warning">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-orange text-white text-center">
                            <h5 class="mb-0">B3 - VPS Standar</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-orange">IDR 15.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>7GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>200% CPU</li>
                            </ul>
                            <a href="order.php?product=B3" class="btn btn-orange">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-danger text-white text-center">
                            <h5 class="mb-0">B4 - VPS Sepuh</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-danger">IDR 20.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>9GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>250% CPU</li>
                            </ul>
                            <a href="order.php?product=B4" class="btn btn-danger">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-purple text-white text-center">
                            <h5 class="mb-0">B5 - VPS Suhu</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-purple">IDR 25.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>11GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>300% CPU</li>
                            </ul>
                            <a href="order.php?product=B5" class="btn btn-purple">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-diamond text-white text-center">
                            <h5 class="mb-0">B6 - VPS Pro Max</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-diamond">IDR 35.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>13GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>350% CPU</li>
                            </ul>
                            <a href="order.php?product=B6" class="btn btn-diamond">Pilih Paket</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Python -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">
                        <i class="fab fa-python text-warning me-2"></i>Python (C1-C6)
                    </h3>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-success text-white text-center">
                            <h5 class="mb-0">C1 - Python Kroco</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-success">IDR 3.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>3GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>100% CPU</li>
                            </ul>
                            <a href="order.php?product=C1" class="btn btn-success">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-warning text-white text-center">
                            <h5 class="mb-0">C2 - Python Karbit</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-warning">IDR 5.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>5GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>150% CPU</li>
                            </ul>
                            <a href="order.php?product=C2" class="btn btn-warning">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-orange text-white text-center">
                            <h5 class="mb-0">C3 - Python Standar</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-orange">IDR 7.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>7GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>150% CPU</li>
                            </ul>
                            <a href="order.php?product=C3" class="btn btn-orange">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-danger text-white text-center">
                            <h5 class="mb-0">C4 - Python Sepuh</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-danger">IDR 10.000</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>9GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>200% CPU</li>
                            </ul>
                            <a href="order.php?product=C4" class="btn btn-danger">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-purple text-white text-center">
                            <h5 class="mb-0">C5 - Python Suhu</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-purple">IDR 12.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>11GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>250% CPU</li>
                            </ul>
                            <a href="order.php?product=C5" class="btn btn-purple">Pilih Paket</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 product-card">
                        <div class="card-header bg-diamond text-white text-center">
                            <h5 class="mb-0">C6 - Python Pro Max</h5>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-diamond">IDR 17.500</h4>
                            <p class="text-muted">per bulan</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-memory me-2"></i>13GB RAM</li>
                                <li><i class="fas fa-microchip me-2"></i>300% CPU</li>
                            </ul>
                            <a href="order.php?product=C6" class="btn btn-diamond">Pilih Paket</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Keunggulan Kami</h2>
                <p class="lead">Mengapa memilih layanan kami?</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                        <h4>Uptime 99%</h4>
                        <p>Server kami memiliki uptime yang stabil dan dapat diandalkan</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                        <h4>DDos Protection</h4>
                        <p>Perlindungan DDos untuk menjaga server tetap aman</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-microchip fa-3x text-warning mb-3"></i>
                        <h4>Epyc Genoa & ECC DDR5</h4>
                        <p>Dijalankan di atas processor terbaru untuk performa maksimal</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-wifi fa-3x text-info mb-3"></i>
                        <h4>Speed Internet Stabil</h4>
                        <p>Koneksi internet stabil hingga 2Gb/s</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-calendar-check fa-3x text-danger mb-3"></i>
                        <h4>Garansi Full 30 Day</h4>
                        <p>Garansi penuh selama 30 hari untuk ketenangan pikiran</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <i class="fas fa-user-shield fa-3x text-purple mb-3"></i>
                        <h4>Admin Panel Aman</h4>
                        <p>Admin panel hanya untuk owner sehingga server aman</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Hubungi Kami</h2>
                <p class="lead">Butuh bantuan? Hubungi kami melalui WhatsApp</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <a href="https://wa.me/62895395590009" class="btn btn-success btn-lg mb-3" target="_blank">
                        <i class="fab fa-whatsapp me-2"></i>WhatsApp: 0895395590009
                    </a>
                    <br>
                    <a href="https://chat.whatsapp.com/I5JCuQnIo4f79JsZAGCvDD?mode=ac_t" class="btn btn-primary btn-lg" target="_blank">
                        <i class="fas fa-users me-2"></i>Join Community
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; 2024 Antidonasi Store. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html> 