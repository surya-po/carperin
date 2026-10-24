<?php
session_start();

$site_logo_img   = "images/23.jpeg";
$qris_image_path = "images/24.jpeg"; 

$link_instagram  = "https://www.instagram.com/carperin.nw?stkn=N295ejljeWw2emFk";
$link_gofood     = "https://gofood.co.id/en/makassar/restaurant/carperin-goa-ria-4189615a-47eb-4a58-bc64-1b0c80225982";
$link_grabfood   = "https://dineout.grab.com/id/id/restaurants/carperin-pattene--6-C7WJDECDNJUELX";
$link_location   = "https://share.google/c7DJPKFdsKNQXvb0Z";

require_once 'koneksi.php';

// Inisialisasi Keranjang
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Tambah Item ke Keranjang
if (isset($_POST['add_to_cart'])) {
    $menu_id = (int)$_POST['menu_id'];
    if (isset($_SESSION['cart'][$menu_id])) {
        $_SESSION['cart'][$menu_id]++;
    } else {
        $_SESSION['cart'][$menu_id] = 1;
    }
    header("Location: index.php#cart-section");
    exit();
}

// Hapus Item dari Keranjang
if (isset($_GET['remove_cart'])) {
    $remove_id = (int)$_GET['remove_cart'];
    unset($_SESSION['cart'][$remove_id]);
    header("Location: index.php#cart-section");
    exit();
}

// Kosongkan Keranjang
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = array();
    header("Location: index.php#cart-section");
    exit();
}

// Batalkan Pesanan / Tutup Tampilan Rincian Pesanan
if (isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    $conn->query("UPDATE orders SET status_pembayaran = 'Dibatalkan' WHERE id = $order_id");
    unset($_SESSION['last_order_id']);
    header("Location: index.php#menu");
    exit();
}

// Proses Buat Pesanan Baru
if (isset($_POST['place_order'])) {
    if (!empty($_SESSION['cart'])) {
        $nama_pemesan      = htmlspecialchars($_POST['nama_pemesan']);
        $metode_pembayaran = htmlspecialchars($_POST['metode_pembayaran']); 
        $opsi_makan        = htmlspecialchars($_POST['opsi_makan']); 
        $nomor_wa          = ""; 
        $kode_pesanan      = "CPR-" . strtoupper(substr(md5(time()), 0, 6));

        $status_pembayaran = ($metode_pembayaran == 'Cash') ? 'Sudah Bayar' : 'Belum Bayar';

        $total_harga = 0;
        $cart_items = array();
        foreach ($_SESSION['cart'] as $id => $qty) {
            $res = $conn->query("SELECT * FROM menu WHERE id = $id");
            if ($res && $item = $res->fetch_assoc()) {
                $subtotal = $item['harga'] * $qty;
                $total_harga += $subtotal;
                $cart_items[] = array('id' => $id, 'harga' => $item['harga'], 'qty' => $qty);
            }
        }

        @$conn->query("ALTER TABLE orders ADD COLUMN metode_pembayaran VARCHAR(50) DEFAULT 'Cash'");
        @$conn->query("ALTER TABLE orders ADD COLUMN bukti_pembayaran VARCHAR(255) DEFAULT NULL");
        @$conn->query("ALTER TABLE orders ADD COLUMN opsi_makan VARCHAR(50) DEFAULT 'Dine-in'");

        $stmt = $conn->prepare("INSERT INTO orders (kode_pesanan, nama_pemesan, nomor_wa, total_harga, status_pembayaran, metode_pembayaran, opsi_makan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssisss", $kode_pesanan, $nama_pemesan, $nomor_wa, $total_harga, $status_pembayaran, $metode_pembayaran, $opsi_makan);
            $stmt->execute();
            $order_id = $stmt->insert_id;

            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, menu_id, jumlah, harga) VALUES (?, ?, ?, ?)");
            if ($stmt_item) {
                foreach ($cart_items as $ci) {
                    $stmt_item->bind_param("iiii", $order_id, $ci['id'], $ci['qty'], $ci['harga']);
                    $stmt_item->execute();
                }
            }

            $_SESSION['last_order_id'] = $order_id;
            $_SESSION['cart'] = array();
        }

        header("Location: index.php#order-detail-section");
        exit();
    }
}

// Upload Bukti Pembayaran QRIS
if (isset($_POST['upload_bukti'])) {
    $order_id = (int)$_POST['order_id'];
    
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['bukti_file']['tmp_name'];
        $fileName      = $_FILES['bukti_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'webp');
        if (in_array($fileExtension, $allowedExtensions)) {
            // Encode ke base64 agar aman & permanen di Vercel Serverless & Supabase
            $mimeType = function_exists('mime_content_type') ? @mime_content_type($fileTmpPath) : '';
            if (!$mimeType) {
                $mimeType = 'image/' . ($fileExtension === 'jpg' ? 'jpeg' : $fileExtension);
            }
            $base64Data = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($fileTmpPath));

            // Jika di localhost dan folder writable, coba simpan juga file fisik
            $uploadFileDir = 'uploads/';
            if (is_dir($uploadFileDir) && is_writable($uploadFileDir)) {
                $newFileName = "bukti_" . $order_id . "_" . time() . "." . $fileExtension;
                @move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName);
            }
            
            $stmt_up = $conn->prepare("UPDATE orders SET bukti_pembayaran = ?, status_pembayaran = 'Sudah Bayar' WHERE id = ?");
            $stmt_up->bind_param("si", $base64Data, $order_id);
            $stmt_up->execute();
        }
    }
    header("Location: index.php#order-detail-section");
    exit();
}

// Submit Ulasan
if (isset($_POST['submit_review'])) {
    $nama   = htmlspecialchars($_POST['nama']);
    $rating = (int)$_POST['rating'];
    $ulasan = htmlspecialchars($_POST['ulasan']);

    $stmt = $conn->prepare("INSERT INTO reviews (nama_pelanggan, rating, ulasan) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sis", $nama, $rating, $ulasan);
        $stmt->execute();
    }
    header("Location: index.php#review");
    exit();
}

// Query Menu & Review
$keyword = "";
if (isset($_GET['q'])) {
    $keyword = $conn->real_escape_string($_GET['q']);
    $sql     = "SELECT * FROM menu WHERE nama_menu LIKE '%$keyword%' OR deskripsi LIKE '%$keyword%'";
} else {
    $sql     = "SELECT * FROM menu";
}
$result_menu = $conn->query($sql);
$result_review = $conn->query("SELECT * FROM reviews ORDER BY created_at DESC");

// Data Pesanan Terakhir
$active_order = null;
if (isset($_SESSION['last_order_id'])) {
    $last_id = (int)$_SESSION['last_order_id'];
    $res_ord = $conn->query("SELECT * FROM orders WHERE id = $last_id");
    if ($res_ord && $res_ord->num_rows > 0) {
        $active_order = $res_ord->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARPERIN - Refreshing Drinks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --primary-dark: #d97706;
            --bg-dark: #121212;
            --card-dark: #1e1e1e;
            --text-light: #f3f4f6;
            --text-muted: #9ca3af;
            --accent-gold: #d4af37;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; scroll-behavior: smooth; }
        body { background-color: var(--bg-dark); color: var(--text-light); line-height: 1.6; }

        /* Header / Navbar Berwarna Tampak Tegas & Kontras */
        header { 
            position: sticky; 
            top: 0; 
            background: #1a1a1a; /* Warna background navbar makin nampak */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6); 
            border-bottom: 2px solid var(--accent-gold); 
            z-index: 1000; 
        }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 12px 3%; max-width: 1320px; margin: 0 auto; }
        .logo { text-decoration: none; display: flex; align-items: center; }
        .logo img { max-height: 38px; border-radius: 6px; object-fit: contain; }
        
        .nav-links { display: flex; list-style: none; gap: 16px; align-items: center; }
        .nav-links a { text-decoration: none; color: #ffffff; font-weight: 600; font-size: 13px; transition: var(--transition); white-space: nowrap; }
        .nav-links a:hover { color: var(--accent-gold); }
        
        .search-form { display: flex; gap: 4px; background: #2a2a2a; padding: 3px; border-radius: 8px; border: 1px solid #3d3d3d; }
        .search-form input { padding: 5px 10px; border: none; background: transparent; outline: none; font-size: 13px; color: var(--text-light); width: 130px; }
        .search-form button { padding: 5px 12px; background: var(--accent-gold); color: #121212; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 12px; transition: var(--transition); }
        .search-form button:hover { background: #f59e0b; }
        
        section { padding: 50px 5%; max-width: 1280px; margin: 0 auto; }
        .section-title { text-align: center; font-size: 32px; color: var(--text-light); margin-bottom: 40px; position: relative; font-weight: 800; letter-spacing: -0.5px; }
        .section-title::after { content: ''; display: block; width: 50px; height: 4px; background: var(--accent-gold); margin: 12px auto 0; border-radius: 2px; }
        
        /* Dark Theme Hero Banner */
        #home { 
            background: linear-gradient(135deg, #1a1a1a 0%, #121212 100%);
            border-radius: 28px; 
            margin: 30px auto; 
            max-width: 1200px; 
            box-shadow: var(--shadow); 
            padding: 60px 40px; 
            border: 1px solid #2a2a2a; 
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .hero-text h1 { font-size: 42px; font-weight: 800; color: #ffffff; margin-bottom: 16px; line-height: 1.2; letter-spacing: -0.5px; }
        .hero-text h1 span { color: var(--accent-gold); }
        .hero-text p { font-size: 15px; color: var(--text-muted); margin-bottom: 25px; line-height: 1.7; }
        
        .hero-card-preview {
            background: var(--card-dark);
            border-radius: 20px;
            padding: 15px;
            border: 1px solid #333;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            text-align: center;
        }
        .hero-card-preview img { width: 100%; height: 260px; object-fit: cover; border-radius: 14px; }

        /* Best Seller Section Styling */
        .bestseller-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 50px; }
        .bestseller-card {
            background: var(--card-dark);
            border-radius: 20px;
            padding: 15px;
            border: 1px solid #2a2a2a;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
        }
        .bestseller-card:hover { transform: translateY(-8px); border-color: var(--accent-gold); }
        .bestseller-card img { width: 100%; height: 200px; object-fit: cover; border-radius: 14px; margin-bottom: 15px; }
        .bestseller-title { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 5px; }
        .bestseller-badge { display: inline-block; background: rgba(212, 175, 55, 0.15); color: var(--accent-gold); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }

        /* Tentang Kami Section */
        #about { background: var(--card-dark); border-radius: 24px; box-shadow: var(--shadow); padding: 50px; margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; border: 1px solid #2a2a2a; }
        .about-content h3 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 16px; }
        .about-content p { color: var(--text-muted); font-size: 15px; margin-bottom: 16px; line-height: 1.7; }
        .about-image img { width: 100%; border-radius: 16px; object-fit: cover; max-height: 300px; box-shadow: var(--shadow); }

        .btn-primary { display: inline-block; padding: 12px 30px; background: var(--accent-gold); color: #121212; text-decoration: none; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; transition: var(--transition); text-align: center; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3); }
        .btn-primary:hover { background: #f59e0b; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: var(--transition); }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: var(--transition); }
        .btn-danger:hover { background: #ef4444; color: white; }

        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .menu-card { background: var(--card-dark); border-radius: 20px; overflow: hidden; box-shadow: var(--shadow); display: flex; flex-direction: column; transition: var(--transition); border: 1px solid #2a2a2a; }
        .menu-card:hover { transform: translateY(-8px); border-color: var(--accent-gold); }
        .menu-card img { width: 100%; height: 220px; object-fit: cover; }
        .menu-info { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .menu-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .menu-price { font-size: 19px; font-weight: 800; color: var(--accent-gold); margin: 12px 0 16px; }

        .cart-box, .review-box { background: var(--card-dark); padding: 35px; border-radius: 24px; box-shadow: var(--shadow); margin-bottom: 30px; border: 1px solid #2a2a2a; }
        .cart-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .cart-table th, .cart-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #2a2a2a; color: var(--text-light); }
        .cart-table th { font-weight: 600; color: var(--text-muted); font-size: 14px; background: #161616; }
        
        .order-card { background: var(--card-dark); border-radius: 24px; padding: 35px; box-shadow: var(--shadow); border: 1px solid #2a2a2a; max-width: 650px; margin: 0 auto; position: relative; }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .order-title { font-size: 22px; font-weight: 800; color: #f472b6; }
        
        .btn-close-order { background: rgba(239, 68, 68, 0.2); color: #ef4444; width: 36px; height: 36px; border-radius: 50%; font-size: 20px; font-weight: bold; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); line-height: 1; }
        .btn-close-order:hover { background: #ef4444; color: #ffffff; }

        .order-info-line { display: flex; font-size: 14px; color: var(--text-light); margin-bottom: 10px; }
        .order-info-line span:first-child { width: 150px; font-weight: 600; color: var(--text-muted); }
        .order-divider { border-top: 1px solid #2a2a2a; margin: 20px 0; }
        .order-summary-row { display: flex; font-size: 15px; margin-bottom: 8px; color: var(--text-light); }
        .order-summary-row.total { font-weight: 800; font-size: 18px; color: #f472b6; margin-top: 12px; }

        .qris-container { text-align: center; background: #1a1a1a; border: 2px dashed var(--accent-gold); border-radius: 16px; padding: 20px; margin-top: 20px; }
        .qris-img { width: 220px; max-width: 100%; border-radius: 12px; border: 1px solid #333; margin: 12px 0; }

        .review-card { background: #161616; padding: 20px 24px; border-radius: 16px; margin-bottom: 16px; border-left: 4px solid var(--accent-gold); border: 1px solid #2a2a2a; }
        .stars { color: #f59e0b; font-size: 15px; }
        
        footer { background: #0b0b0b; color: white; text-align: center; padding: 40px 20px; margin-top: 60px; border-top: 1px solid #2a2a2a; }

        @media (max-width: 900px) {
            #home, #about, .bestseller-container { grid-template-columns: 1fr; padding: 30px 20px; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>

    <header>
        <div class="navbar">
            <a href="#home" class="logo">
                <img src="<?php echo $site_logo_img; ?>" alt="Logo Carperin" onerror="this.style.display='none'">
            </a>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#bestseller">Best Seller</a></li>
                <li><a href="#about">Tentang Kami</a></li>
                <li><a href="#menu">Menu</a></li>
                <li><a href="#cart-section">Keranjang (<?php echo array_sum($_SESSION['cart']); ?>)</a></li>
                <li><a href="#review">Ulasan</a></li>
                <li><a href="admin.php" style="color:var(--accent-gold); font-weight:700;">Portal Admin</a></li>
            </ul>
            <form action="index.php#menu" method="GET" class="search-form">
                <input type="text" name="q" placeholder="Cari menu..." value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit">Cari</button>
            </form>
        </div>
    </header>

    <!-- Dark Theme Hero Banner Sesuai Referensi -->
    <section id="home">
        <div class="hero-text">
            <h1>Enjoy The Most <span>Delicious Drinks</span></h1>
            <p>Start Your Day With Refreshing Beverages, Enhancing Productivity And Mood. Our Invigorating Drinks Set A Focused Tone For Tackling Tasks With Renewed Energy And Positivity.</p>
            <a href="#menu" class="btn-primary">Explore Menu</a>
        </div>
        <div class="hero-card-preview">
            <img src="images/25.jpeg" alt="Featured Drink" onerror="this.src='https://via.placeholder.com/400x260?text=CARPERIN+Drink'">
        </div>
    </section>

    <!-- Best Seller Section -->
    <section id="bestseller">
        <h2 class="section-title">Menu Best Seller</h2>
        <div class="bestseller-container">
            <div class="bestseller-card">
                <img src="images/10.jpeg" alt="Best Seller 1" onerror="this.src='https://via.placeholder.com/300x200?text=Best+Seller+1'">
                <span class="bestseller-badge">⭐ Favorite Choice</span>
                <div class="bestseller-title">Koar (Kopi Gula Aren)</div>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">Perpaduan kopi dan gula aren yang nikmat.</p>
                <a href="#menu" class="btn-primary" style="padding: 8px 20px; font-size: 13px;">Pesan Sekarang</a>
            </div>
            <div class="bestseller-card">
                <img src="images/5.jpeg" alt="Best Seller 2" onerror="this.src='https://via.placeholder.com/300x200?text=Best+Seller+2'">
                <span class="bestseller-badge">⭐ Most Popular</span>
                <div class="bestseller-title">Alpukat Milo</div>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">Perpaduan antara alpukat dan coklat dengan toping milo.</p>
                <a href="#menu" class="btn-primary" style="padding: 8px 20px; font-size: 13px;">Pesan Sekarang</a>
            </div>
            <div class="bestseller-card">
                <img src="images/12.jpeg" alt="Best Seller 3" onerror="this.src='https://via.placeholder.com/300x200?text=Best+Seller+3'">
                <span class="bestseller-badge">⭐ Top Rated</span>
                <div class="bestseller-title">Matcha Creamy</div>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">Perpaduan milk dan creamy matcha.</p>
                <a href="#menu" class="btn-primary" style="padding: 8px 20px; font-size: 13px;">Pesan Sekarang</a>
            </div>
        </div>
    </section>

    <!-- Tentang Kami Section -->
    <section id="about">
        <div class="about-content">
            <h3>Tentang CARPERIN</h3>
            <p><strong>CARPERIN</strong> hadir sebagai destinasi utama minuman menyegarkan berkualitas tinggi yang dirancang khusus untuk menemani hari-hari Anda. Kami menyajikan berbagai varian rasa premium dengan proses pembuatan yang higienis, cepat, dan praktis.</p>
            <p>Komitmen kami adalah memberikan pengalaman kuliner terbaik bagi setiap pelanggan, baik melalui layanan langsung di tempat (*dine-in*), bawa pulang (*takeaway*), maupun pemesanan digital yang terintegrasi penuh.</p>
            <a href="#menu" class="btn-primary" style="margin-top: 10px;">Jelajahi Menu Kami</a>
        </div>
        <div class="about-image">
            <img src="<?php echo $site_logo_img; ?>" alt="Tentang Carperin" onerror="this.src='https://via.placeholder.com/500x320?text=CARPERIN'">
        </div>
    </section>

    <!-- Rincian Pesanan Aktif -->
    <?php if ($active_order): 
        $metode = !empty($active_order['metode_pembayaran']) ? $active_order['metode_pembayaran'] : 'Cash';
        $opsi = !empty($active_order['opsi_makan']) ? $active_order['opsi_makan'] : 'Dine-in';

        if ($active_order['status_pembayaran'] == 'Dibatalkan') {
            $st_bayar = 'Dibatalkan';
        } else if ($metode == 'Cash' || !empty($active_order['bukti_pembayaran'])) {
            $st_bayar = 'Sudah Bayar';
        } else {
            $st_bayar = !empty($active_order['status_pembayaran']) ? $active_order['status_pembayaran'] : 'Belum Bayar';
        }
    ?>
    <section id="order-detail-section">
        <div class="order-card">
            <div class="order-header">
                <div class="order-title">Pesanan #<?php echo $active_order['id']; ?></div>
                <form action="index.php" method="POST" style="margin:0;">
                    <input type="hidden" name="order_id" value="<?php echo $active_order['id']; ?>">
                    <button type="submit" name="cancel_order" class="btn-close-order" title="Tutup / Batalkan Pesanan">&times;</button>
                </form>
            </div>

            <div class="order-info-line">
                <span>Konsumen:</span>
                <span><?php echo htmlspecialchars($active_order['nama_pemesan']); ?></span>
            </div>
            <div class="order-info-line">
                <span>Tanggal:</span>
                <span><?php echo isset($active_order['created_at']) ? date("d M Y H:i", strtotime($active_order['created_at'])) : date("d M Y H:i"); ?></span>
            </div>
            <div class="order-info-line">
                <span>Opsi Layanan:</span>
                <span><?php echo htmlspecialchars($opsi); ?></span>
            </div>
            <div class="order-info-line">
                <span>Pembayaran:</span>
                <span><?php echo htmlspecialchars($metode); ?></span>
            </div>

            <div class="order-divider"></div>

            <div class="order-summary-row">
                <span style="flex:1;">Total Produk:</span>
                <span>Rp <?php echo number_format($active_order['total_harga'], 0, ',', '.'); ?></span>
            </div>
            <div class="order-summary-row">
                <span style="flex:1;">Ongkir:</span>
                <span>Rp 0</span>
            </div>
            <div class="order-summary-row total">
                <span style="flex:1;">Total Keseluruhan:</span>
                <span>Rp <?php echo number_format($active_order['total_harga'], 0, ',', '.'); ?></span>
            </div>

            <div class="order-divider"></div>

            <div class="order-info-line" style="align-items: center; justify-content: space-between;">
                <span>Status Pembayaran:</span>
                <span style="font-weight: 800; font-size: 15px; color: <?php echo ($st_bayar == 'Sudah Bayar') ? '#10b981' : (($st_bayar == 'Dibatalkan') ? '#ef4444' : '#f59e0b'); ?>;">
                    <?php echo htmlspecialchars($st_bayar); ?>
                </span>
            </div>

            <?php if ($metode == 'QRIS' && $st_bayar == 'Belum Bayar' && empty($active_order['bukti_pembayaran'])): ?>
                <div class="qris-container">
                    <p style="font-weight:700; color:var(--accent-gold); font-size:15px; margin-bottom:8px;">Silakan Scan QRIS di Bawah Ini</p>
                    <img src="<?php echo $qris_image_path; ?>" alt="QRIS CARPERIN" class="qris-img" onerror="this.src='https://via.placeholder.com/220x220?text=Scan+QRIS+Di+Sini'">
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">Upload bukti transfer / screenshot setelah pembayaran:</p>
                    
                    <form action="index.php" method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px; align-items:center;">
                        <input type="hidden" name="order_id" value="<?php echo $active_order['id']; ?>">
                        <input type="file" name="bukti_file" accept="image/*" required style="font-size:13px; background:#222; color:#fff; padding:8px; border-radius:8px; border:1px solid #333; width:100%; max-width:320px;">
                        <button type="submit" name="upload_bukti" class="btn-success" style="width:100%; max-width:320px; padding:10px;">
                            📤 Upload Bukti Pembayaran
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($active_order['bukti_pembayaran'])): ?>
                <div style="margin-top: 20px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 14px; padding: 16px; text-align: center;">
                    <p style="color: #34d399; font-weight: 700; font-size: 14px; margin-bottom: 10px;">✅ Bukti Pembayaran Berhasil Disimpan:</p>
                    <img src="<?php echo get_image_url($active_order['bukti_pembayaran']); ?>" alt="Bukti Pembayaran" style="max-width: 100%; max-height: 250px; border-radius: 10px; border: 1px solid #10b981;">
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Menu Section -->
    <section id="menu">
        <h2 class="section-title">Daftar Menu</h2>
        <div class="menu-grid">
            <?php
            if ($result_menu && $result_menu->num_rows > 0) {
                while($row = $result_menu->fetch_assoc()) {
                    $gambar = !empty($row['gambar']) ? $row['gambar'] : 'default.jpeg';
                    $image_file = "images/" . $gambar;
                    
                    if (!file_exists($image_file)) {
                        $image_file = "https://via.placeholder.com/300x220?text=" . urlencode($row['nama_menu']);
                    }

                    echo '
                    <div class="menu-card">
                        <img src="' . $image_file . '" alt="' . htmlspecialchars($row['nama_menu']) . '">
                        <div class="menu-info">
                            <div>
                                <div class="menu-title">' . htmlspecialchars($row['nama_menu']) . '</div>
                                <div style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">' . htmlspecialchars($row['deskripsi']) . '</div>
                                <div class="menu-price">Rp ' . number_format($row['harga'], 0, ',', '.') . '</div>
                            </div>
                            <form action="index.php#cart-section" method="POST">
                                <input type="hidden" name="menu_id" value="' . $row['id'] . '">
                                <button type="submit" name="add_to_cart" class="btn-primary" style="width:100%; padding:12px;">+ Masukkan Keranjang</button>
                            </form>
                        </div>
                    </div>';
                }
            } else {
                echo '<p style="grid-column: 1/-1; text-align: center; color:var(--text-muted);">Menu tidak ditemukan.</p>';
            }
            ?>
        </div>
    </section>

    <!-- Keranjang & Checkout Section -->
    <section id="cart-section">
        <h2 class="section-title">Keranjang Belanja Anda</h2>
        <div class="cart-box">
            <?php if (!empty($_SESSION['cart'])): ?>
                <div style="overflow-x: auto;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grand_total = 0;
                            foreach ($_SESSION['cart'] as $id => $qty):
                                $res = $conn->query("SELECT * FROM menu WHERE id = $id");
                                if ($res && $item = $res->fetch_assoc()):
                                    $subtotal = $item['harga'] * $qty;
                                    $grand_total += $subtotal;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['nama_menu']); ?></strong></td>
                                <td>Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                <td><?php echo $qty; ?></td>
                                <td>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                                <td><a href="index.php?remove_cart=<?php echo $id; ?>" class="btn-danger">Hapus</a></td>
                            </tr>
                            <?php endif; endforeach; ?>
                            <tr>
                                <td colspan="3" style="text-align:right;"><strong>Total Pembayaran:</strong></td>
                                <td colspan="2"><strong style="color:var(--accent-gold); font-size:18px;">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-top:20px;">
                    <a href="index.php?clear_cart=1" style="color:#ef4444; font-size:14px; font-weight:600; text-decoration:none;">Kosongkan Keranjang</a>
                    
                    <form action="index.php" method="POST" style="display:flex; gap:12px; flex-wrap:wrap; width:100%;">
                        <input type="text" name="nama_pemesan" placeholder="Nama Pemesan" required style="padding:12px 16px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; flex:2; min-width:200px; outline:none; font-size:14px;">
                        
                        <select name="opsi_makan" required style="padding:12px 16px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; flex:1; min-width:150px; font-weight:600; outline:none; font-size:14px;">
                            <option value="Dine-in" selected>🍽️ Dine-in</option>
                            <option value="Takeaway">🛍️ Takeaway</option>
                        </select>

                        <select name="metode_pembayaran" required style="padding:12px 16px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; flex:1; min-width:150px; font-weight:600; outline:none; font-size:14px;">
                            <option value="Cash">💵 Cash (Tunai)</option>
                            <option value="QRIS" selected>📱 QRIS (Nontunai)</option>
                        </select>

                        <button type="submit" name="place_order" class="btn-success" style="flex:1; min-width:180px; padding:12px;">Buat Pesanan</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:var(--text-muted); padding:20px 0;">Keranjang belanja Anda masih kosong. Silakan pilih menu di atas.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Review Section -->
    <section id="review">
        <h2 class="section-title">Ulasan Pelanggan</h2>
        <div class="review-box">
            <h3 style="font-size:20px; font-weight:700; margin-bottom:15px; color:#fff;">Beri Ulasan Anda</h3>
            <form action="index.php#review" method="POST">
                <input type="text" name="nama" placeholder="Nama Anda" required style="width:100%; padding:12px 16px; margin-bottom:12px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; outline:none; font-size:14px;">
                <select name="rating" required style="width:100%; padding:12px 16px; margin-bottom:12px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; outline:none; font-size:14px;">
                    <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                    <option value="4">⭐⭐⭐⭐ (4/5)</option>
                    <option value="3">⭐⭐⭐ (3/5)</option>
                    <option value="2">⭐⭐ (2/5)</option>
                    <option value="1">⭐ (1/5)</option>
                </select>
                <textarea name="ulasan" rows="3" placeholder="Tuliskan ulasan Anda..." required style="width:100%; padding:12px 16px; margin-bottom:15px; border:1px solid #333; background:#222; color:#fff; border-radius:10px; outline:none; font-size:14px;"></textarea>
                <button type="submit" name="submit_review" class="btn-primary">Kirim Ulasan</button>
            </form>
        </div>

        <h3 style="font-size:22px; font-weight:700; margin-bottom:20px; color:#fff;">Ulasan Terbaru</h3>
        <div>
            <?php
            if ($result_review && $result_review->num_rows > 0) {
                while($rev = $result_review->fetch_assoc()) {
                    echo '
                    <div class="review-card">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="font-size:16px; color:#fff;">' . htmlspecialchars($rev['nama_pelanggan']) . '</strong>
                            <span class="stars">' . str_repeat('⭐', $rev['rating']) . '</span>
                        </div>
                        <p style="color:var(--text-muted); font-size:14px; margin-top:8px;">' . htmlspecialchars($rev['ulasan']) . '</p>
                        <small style="color:#6b7280; display:block; margin-top:8px;">' . $rev['created_at'] . '</small>
                    </div>';
                }
            } else {
                echo '<p style="color:var(--text-muted);">Belum ada ulasan.</p>';
            }
            ?>
        </div>
    </section>

    <footer>
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 20px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;">
                <a href="<?php echo $link_instagram; ?>" target="_blank" style="background: #E1306C; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: var(--transition);">
                    📸 Instagram
                </a>
                <a href="<?php echo $link_gofood; ?>" target="_blank" style="background: #00AA13; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: var(--transition);">
                    🟢 GoFood
                </a>
                <a href="<?php echo $link_grabfood; ?>" target="_blank" style="background: #00B14F; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: var(--transition);">
                    🟩 GrabFood
                </a>
                <a href="<?php echo $link_location; ?>" target="_blank" style="background: #EA4335; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: var(--transition);">
                    📍 Lokasi Kami
                </a>
            </div>
            
            <p style="font-size: 14px; color: #6b7280; margin-top: 5px;">&copy; <?php echo date("Y"); ?> CARPERIN. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>