# Antidonasi Store - Panel Pterodactyl Private

Aplikasi web panel Pterodactyl store yang menyediakan layanan server hosting dengan sistem semi-auto provision. Aplikasi ini terintegrasi dengan API Pterodactyl untuk pembuatan server otomatis.

## 🚀 Fitur Utama

### Untuk User
- **Landing Page** - Halaman utama dengan showcase produk
- **Login/Register** - Sistem autentikasi user
- **Dashboard User** - Panel kontrol untuk user
- **Order System** - Sistem pemesanan dengan QRIS payment
- **Order History** - Riwayat order user
- **Server Management** - Kelola server yang dimiliki
- **Profile Management** - Edit profil user

### Untuk Admin
- **Admin Dashboard** - Panel kontrol admin dengan statistik
- **Order Management** - Kelola semua order dari user
- **User Management** - Kelola user yang terdaftar
- **Product Management** - Kelola produk yang tersedia
- **Server Management** - Monitor dan kelola server
- **Payment Confirmation** - Konfirmasi pembayaran manual

### Integrasi Pterodactyl
- **Auto Provision** - Pembuatan server otomatis setelah konfirmasi
- **API Integration** - Terhubung dengan PTLA dan PTLC API
- **Server Monitoring** - Monitor status server real-time

## 📋 Daftar Produk

### NodeJS VIP (A1-A6)
- **A1** - NodeJS Kroco: 3GB RAM, 100% CPU - IDR 5.000/bulan
- **A2** - NodeJS Karbit: 5GB RAM, 150% CPU - IDR 7.500/bulan
- **A3** - NodeJS Standar: 7GB RAM, 200% CPU - IDR 10.000/bulan
- **A4** - NodeJS Sepuh: 11GB RAM, 250% CPU - IDR 12.500/bulan
- **A5** - NodeJS Suhu: 13GB RAM, 300% CPU - IDR 15.000/bulan
- **A6** - NodeJS Pro Max: 16GB RAM, 400% CPU - IDR 20.000/bulan

### VPS (B1-B6)
- **B1** - VPS Kroco: 3GB RAM, 100% CPU - IDR 7.500/bulan
- **B2** - VPS Karbit: 5GB RAM, 150% CPU - IDR 10.000/bulan
- **B3** - VPS Standar: 7GB RAM, 200% CPU - IDR 15.000/bulan
- **B4** - VPS Sepuh: 9GB RAM, 250% CPU - IDR 20.000/bulan
- **B5** - VPS Suhu: 11GB RAM, 300% CPU - IDR 25.000/bulan
- **B6** - VPS Pro Max: 13GB RAM, 350% CPU - IDR 35.000/bulan

### Python (C1-C6)
- **C1** - Python Kroco: 3GB RAM, 100% CPU - IDR 3.000/bulan
- **C2** - Python Karbit: 5GB RAM, 150% CPU - IDR 5.000/bulan
- **C3** - Python Standar: 7GB RAM, 150% CPU - IDR 7.500/bulan
- **C4** - Python Sepuh: 9GB RAM, 200% CPU - IDR 10.000/bulan
- **C5** - Python Suhu: 11GB RAM, 250% CPU - IDR 12.500/bulan
- **C6** - Python Pro Max: 13GB RAM, 300% CPU - IDR 17.500/bulan

## 🛠️ Tech Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, jQuery
- **Payment**: QRIS Integration
- **API**: Pterodactyl API (PTLA & PTLC)

## 📦 Instalasi

### Prerequisites
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web server (Apache/Nginx)
- Composer (untuk dependency management)

### Langkah Instalasi

1. **Clone Repository**
   ```bash
   git clone https://github.com/yourusername/pterodactyl-store.git
   cd pterodactyl-store
   ```

2. **Setup Database**
   ```sql
   CREATE DATABASE pterodactyl_store;
   ```

3. **Konfigurasi Database**
   Edit file `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'pterodactyl_store');
   ```

4. **Konfigurasi Pterodactyl**
   Edit file `config/pterodactyl.php`:
   ```php
   define('PTERODACTYL_URL', 'https://your-pterodactyl-panel.com');
   define('PTERODACTYL_API_KEY', 'your-ptla-api-key');
   define('PTERODACTYL_CLIENT_API_KEY', 'your-ptlc-api-key');
   ```

5. **Setup Web Server**
   - Point document root ke direktori aplikasi
   - Pastikan mod_rewrite aktif (untuk Apache)
   - Set permission yang tepat untuk uploads folder

6. **Akses Aplikasi**
   - Buka browser dan akses aplikasi
   - Database dan tabel akan dibuat otomatis
   - Login admin default: `admin` / `admin123`

## 🔧 Konfigurasi

### QRIS Integration
Untuk mengintegrasikan dengan QRIS, edit file `order_confirmation.php` dan ganti placeholder QRIS dengan API yang sebenarnya.

### Pterodactyl API
Pastikan API key yang digunakan memiliki permission yang tepat:
- **PTLA (Application API)**: Untuk membuat user dan server
- **PTLC (Client API)**: Untuk akses client ke server

### Email Configuration
Untuk notifikasi email, konfigurasi SMTP di file yang sesuai.

## 📁 Struktur Direktori

```
pterodactyl-store/
├── admin/                 # Admin panel
│   ├── dashboard.php
│   ├── orders.php
│   ├── users.php
│   ├── products.php
│   └── servers.php
├── user/                  # User panel
│   ├── dashboard.php
│   ├── orders.php
│   ├── servers.php
│   └── profile.php
├── config/                # Configuration files
│   ├── database.php
│   └── pterodactyl.php
├── assets/                # Static assets
│   ├── css/
│   └── js/
├── uploads/               # Upload directory
├── index.php              # Landing page
├── login.php              # Login page
├── register.php           # Register page
├── order.php              # Order page
├── order_confirmation.php # Payment confirmation
└── logout.php             # Logout handler
```

## 🔐 Security Features

- **Password Hashing**: Menggunakan `password_hash()` dengan bcrypt
- **SQL Injection Protection**: Menggunakan prepared statements
- **XSS Protection**: Output escaping dengan `htmlspecialchars()`
- **CSRF Protection**: Session-based protection
- **Input Validation**: Server-side validation untuk semua input

## 💳 Payment Flow

1. **User membuat order** → Halaman order dengan detail produk
2. **Konfirmasi order** → Halaman pembayaran QRIS
3. **Upload bukti pembayaran** → User upload screenshot
4. **Admin review** → Admin konfirmasi pembayaran
5. **Auto provision** → Server dibuat otomatis di Pterodactyl
6. **User notification** → User mendapat info akses server

## 🚀 Deployment

### Production Checklist
- [ ] Set `display_errors = Off` di php.ini
- [ ] Konfigurasi SSL certificate
- [ ] Setup backup database otomatis
- [ ] Konfigurasi monitoring server
- [ ] Setup error logging
- [ ] Optimize database queries
- [ ] Setup CDN untuk static assets

### Performance Optimization
- Enable PHP OPcache
- Configure MySQL query cache
- Use CDN for Bootstrap and jQuery
- Optimize images
- Enable gzip compression

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Periksa konfigurasi database di `config/database.php`
   - Pastikan MySQL service berjalan

2. **Pterodactyl API Error**
   - Periksa API key dan URL di `config/pterodactyl.php`
   - Pastikan API key memiliki permission yang tepat

3. **Upload Error**
   - Periksa permission folder uploads
   - Periksa `upload_max_filesize` di php.ini

4. **QRIS Integration**
   - Ganti placeholder QRIS dengan API yang sebenarnya
   - Test payment flow di environment development

## 📞 Support

- **WhatsApp**: 0895395590009
- **Community**: https://chat.whatsapp.com/I5JCuQnIo4f79JsZAGCvDD?mode=ac_t
- **Email**: support@antidonasi.com

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📈 Roadmap

- [ ] Multi-language support
- [ ] Mobile app
- [ ] Advanced server monitoring
- [ ] Automated backup system
- [ ] API documentation
- [ ] WebSocket integration for real-time updates
- [ ] Advanced analytics dashboard
- [ ] Multi-payment gateway support

---

**Antidonasi Store** - Panel Pterodactyl Private dengan performa terbaik dan uptime 99%! 