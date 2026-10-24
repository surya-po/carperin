<?php
require_once 'config.php';

// Proteksi Sesi Login
if (!isset($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'menu';

// 
// -------------------------------------------------------------
// 1. EKSPOR LAPORAN PENJUALAN KE CSV
// -------------------------------------------------------------
if (isset($_GET['export_csv'])) {
    $lap_start = $_GET['start_date'] ?? '';
    $lap_end   = $_GET['end_date'] ?? '';
    $where_lap = (!empty($lap_start) && !empty($lap_end)) ? " WHERE DATE(created_at) BETWEEN '$lap_start' AND '$lap_end'" : "";

    $query_export = $conn->query("SELECT * FROM orders $where_lap ORDER BY created_at DESC");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Penjualan_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID Order', 'Nama Pemesan', 'Metode Pembayaran', 'Total Harga', 'Status Pembayaran', 'Tanggal'));

    if ($query_export && $query_export->num_rows > 0) {
        while ($row = $query_export->fetch_assoc()) {
            fputcsv($output, array(
                $row['id'], 
                $row['nama_pemesan'], 
                $row['metode_pembayaran'] ?? '-', 
                $row['total_harga'], 
                $row['status_pembayaran'] ?? 'Belum Dibayar', 
                $row['created_at']
            ));
        }
    }
    fclose($output);
    exit();
}

// -------------------------------------------------------------
// 2. IMPOR LAPORAN PENJUALAN DARI CSV
// -------------------------------------------------------------
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']['tmp_name']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        fgetcsv($handle); // Abaikan baris header CSV

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $nama_pemesan = $conn->real_escape_string($data[1]);
            $metode        = $conn->real_escape_string($data[2]);
            $total_harga  = (float)$data[3];
            $status        = $conn->real_escape_string($data[4]);
            $created_at   = $conn->real_escape_string($data[5]);

            $conn->query("INSERT INTO orders (nama_pemesan, metode_pembayaran, total_harga, status_pembayaran, created_at) 
                          VALUES ('$nama_pemesan', '$metode', '$total_harga', '$status', '$created_at')");
        }
        fclose($handle);
        header("Location: admin.php?tab=laporan");
        exit();
    }
}

// -------------------------------------------------------------
// 3. LOGIKA CRUD KELOLA MENU (TAMBAH, EDIT, HAPUS + GAMBAR)
// -------------------------------------------------------------
if (isset($_POST['simpan_menu'])) {
    $nama      = $conn->real_escape_string($_POST['nama_menu']);
    $kategori  = $conn->real_escape_string($_POST['kategori']);
    $harga     = $_POST['harga'];
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $gambar    = $_POST['gambar_lama'] ?? '';

    if (isset($_FILES['gambar']['name']) && $_FILES['gambar']['name'] != '' && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['gambar']['tmp_name'];
        $ext     = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $mime    = function_exists('mime_content_type') ? @mime_content_type($fileTmp) : '';
        if (!$mime) {
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        }
        $gambar  = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fileTmp));
    }

    if (!empty($_POST['id_menu'])) {
        $id = $_POST['id_menu'];
        $conn->query("UPDATE menu SET nama_menu='$nama', kategori='$kategori', harga='$harga', gambar='$gambar', deskripsi='$deskripsi' WHERE id='$id'");
    } else {
        $conn->query("INSERT INTO menu (nama_menu, kategori, harga, gambar, deskripsi) VALUES ('$nama', '$kategori', '$harga', '$gambar', '$deskripsi')");
    }
    header("Location: admin.php?tab=menu");
    exit();
}

if (isset($_GET['hapus_menu'])) {
    $id = $_GET['hapus_menu'];
    $conn->query("DELETE FROM menu WHERE id='$id'");
    header("Location: admin.php?tab=menu");
    exit();
}

// -------------------------------------------------------------
// 4. LOGIKA UPDATE STATUS PESANAN
// -------------------------------------------------------------
if (isset($_POST['update_status'])) {
    $id_order = $_POST['id_order'];
    $status   = $_POST['status_pembayaran'];
    $conn->query("UPDATE orders SET status_pembayaran='$status' WHERE id='$id_order'");
    header("Location: admin.php?tab=pesanan");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - Carperin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #334155; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #0f172a; color: #fff; padding: 24px; flex-shrink: 0; display: flex; flex-direction: column; justify-content: space-between; }
        .sidebar h2 { font-size: 20px; margin-bottom: 30px; color: #38bdf8; text-transform: uppercase; }
        .sidebar ul { list-style: none; }
        .sidebar li { margin-bottom: 10px; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 12px 16px; border-radius: 8px; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background-color: #0284c7; color: #fff; }
        .btn-toko { background: #16a34a !important; color: white !important; margin-top: 15px; text-align: center; }
        .btn-logout { background: #ef4444 !important; color: white !important; text-align: center; margin-top: 10px; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .top-header { margin-bottom: 24px; }
        .top-header h1 { font-size: 24px; color: #1e293b; }
        .form-card, .table-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; }
        .img-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-lunas { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fee2e2; color: #dc2626; }
        .btn-action { padding: 8px 14px; background: #0284c7; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .btn-success { background: #16a34a; }
        .btn-danger { background: #ef4444; }
        .check-icon { color: #16a34a; font-size: 20px; font-weight: bold; }
        .cross-icon { color: #ef4444; font-size: 20px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <h2>Carperin Admin</h2>
            <ul>
                <li><a href="admin.php?tab=menu" class="<?php echo ($active_tab == 'menu') ? 'active' : ''; ?>">🍱 Kelola Menu</a></li>
                <li><a href="admin.php?tab=pesanan" class="<?php echo ($active_tab == 'pesanan') ? 'active' : ''; ?>">🛒 Data Pesanan</a></li>
                <li><a href="admin.php?tab=laporan" class="<?php echo ($active_tab == 'laporan') ? 'active' : ''; ?>">📊 Laporan Penjualan</a></li>
            </ul>
        </div>
        <div>
            <a href="index.php" class="btn-toko">🏪 Kembali ke Toko</a>
            <a href="logout.php" class="btn-logout">🚪 Logout</a>
        </div>
    </div>

    <div class="main-content">

        <!-- TAB 1: KELOLA MENU -->
        <?php if ($active_tab == 'menu'): ?>
            <?php
                $edit_data = null;
                if (isset($_GET['edit_menu'])) {
                    $id_edit   = $_GET['edit_menu'];
                    $edit_data = $conn->query("SELECT * FROM menu WHERE id='$id_edit'")->fetch_assoc();
                }
                $query_menu = $conn->query("SELECT * FROM menu ORDER BY id DESC");
            ?>
            <div class="top-header">
                <h1>Kelola Menu & Produk</h1>
            </div>

            <div class="form-card">
                <form action="admin.php?tab=menu" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_menu" value="<?php echo $edit_data['id'] ?? ''; ?>">
                    <input type="hidden" name="gambar_lama" value="<?php echo $edit_data['gambar'] ?? ''; ?>">
                    
                    <div style="display:flex; gap:15px; flex-wrap:wrap;">
                        <div class="form-group" style="flex:2;">
                            <label>Nama Menu</label>
                            <input type="text" name="nama_menu" value="<?php echo $edit_data['nama_menu'] ?? ''; ?>" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Kategori</label>
                            <input type="text" name="kategori" value="<?php echo $edit_data['kategori'] ?? ''; ?>" required placeholder="Coffee/Milk/Tea">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Harga (Rp)</label>
                            <input type="number" name="harga" value="<?php echo $edit_data['harga'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <div style="display:flex; gap:15px; flex-wrap:wrap;">
                        <div class="form-group" style="flex:2;">
                            <label>Deskripsi</label>
                            <textarea name="deskripsi" rows="2"><?php echo $edit_data['deskripsi'] ?? ''; ?></textarea>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Gambar Produk</label>
                            <input type="file" name="gambar" accept="image/*">
                        </div>
                    </div>

                    <button type="submit" name="simpan_menu" class="btn-action">
                        <?php echo $edit_data ? '✏️ Update Menu' : '➕ Tambah Menu Baru'; ?>
                    </button>
                    <?php if($edit_data): ?>
                        <a href="admin.php?tab=menu" class="btn-action" style="background:#64748b;">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($m = $query_menu->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <img src="<?php echo get_image_url($m['gambar']); ?>" class="img-thumb" alt="gambar" onerror="this.src='https://via.placeholder.com/50'">
                            </td>
                            <td><strong><?php echo htmlspecialchars($m['nama_menu']); ?></strong></td>
                            <td><?php echo htmlspecialchars($m['kategori']); ?></td>
                            <td>Rp <?php echo number_format($m['harga'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($m['deskripsi']); ?></td>
                            <td>
                                <a href="admin.php?tab=menu&edit_menu=<?php echo $m['id']; ?>" class="btn-action">Edit</a>
                                <a href="admin.php?tab=menu&hapus_menu=<?php echo $m['id']; ?>" class="btn-action btn-danger" onclick="return confirm('Hapus menu ini?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- TAB 2: DATA PESANAN -->
        <?php if ($active_tab == 'pesanan'): ?>
            <?php $query_pesanan = $conn->query("SELECT * FROM orders ORDER BY created_at DESC"); ?>
            <div class="top-header">
                <h1>Data Pesanan Pelanggan</h1>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>ID Order</th>
                            <th>Nama Pemesan</th>
                            <th>Metode Pembayaran</th>
                            <th>Total Harga</th>
                            <th>Bukti Pembayaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ord = $query_pesanan->fetch_assoc()): 
                            $st = $ord['status_pembayaran'] ?? 'Belum Dibayar';
                            // Normalisasi status lama jika ada
                            if ($st == 'Sudah Bayar' || $st == 'Lunas' || $st == 'Selesai') {
                                $st = 'Sudah Dibayar';
                            }
                        ?>
                        <tr>
                            <td>#<?php echo $ord['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($ord['nama_pemesan']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ord['metode_pembayaran'] ?? '-'); ?></td>
                            <td>Rp <?php echo number_format($ord['total_harga'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if(!empty($ord['bukti_pembayaran'])): ?>
                                    <a href="<?php echo get_image_url($ord['bukti_pembayaran']); ?>" target="_blank">
                                        <img src="<?php echo get_image_url($ord['bukti_pembayaran']); ?>" class="img-thumb">
                                    </a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Tidak Ada</span>
                                <?php endif; ?>
                            </td>
                            <!-- Indikator Centang Hijau atau Silang Merah -->
                            <td>
                                <?php if ($st == 'Sudah Dibayar'): ?>
                                    <span class="check-icon">&#10004;</span> <span style="color:#15803d; font-weight:600;">Sudah Dibayar</span>
                                <?php else: ?>
                                    <span class="cross-icon">&#10006;</span> <span class="badge badge-pending">Tidak Berhasil / Belum</span>
                                <?php endif; ?>
                            </td>
                            <!-- Pilihan Ubah Status -->
                            <td>
                                <form action="admin.php?tab=pesanan" method="POST" style="display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="id_order" value="<?php echo $ord['id']; ?>">
                                    <select name="status_pembayaran">
                                        <option value="Sudah Dibayar" <?php echo ($st == 'Sudah Dibayar') ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                        <option value="Belum Dibayar" <?php echo ($st != 'Sudah Dibayar') ? 'selected' : ''; ?>>Tidak Berhasil / Belum</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-action">Simpan</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- TAB 3: LAPORAN PENJUALAN -->
        <?php if ($active_tab == 'laporan'): ?>
            <?php
                $lap_start = $_GET['start_date'] ?? '';
                $lap_end   = $_GET['end_date'] ?? '';
                $where_lap = (!empty($lap_start) && !empty($lap_end)) ? " WHERE DATE(created_at) BETWEEN '$lap_start' AND '$lap_end'" : "";

                $query_laporan = $conn->query("SELECT * FROM orders $where_lap ORDER BY created_at DESC");
                $total_omset   = $conn->query("SELECT SUM(total_harga) as total FROM orders $where_lap")->fetch_assoc()['total'] ?? 0;
            ?>

            <div class="top-header">
                <h1>Laporan Penjualan</h1>
            </div>

            <div class="form-card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
                <form action="admin.php" method="GET" style="display:flex; gap:10px; align-items:flex-end;">
                    <input type="hidden" name="tab" value="laporan">
                    <div class="form-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo $lap_start; ?>">
                    </div>
                    <div class="form-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo $lap_end; ?>">
                    </div>
                    <button type="submit" class="btn-action">Filter</button>
                </form>

                <div style="display:flex; gap:10px;">
                    <a href="admin.php?export_csv=1&start_date=<?php echo $lap_start; ?>&end_date=<?php echo $lap_end; ?>" class="btn-action btn-success">📥 Ekspor CSV</a>
                    <form action="admin.php?tab=laporan" method="POST" enctype="multipart/form-data" style="display:flex; gap:6px;">
                        <input type="file" name="csv_file" accept=".csv" required style="font-size:12px;">
                        <button type="submit" name="import_csv" class="btn-action">📤 Impor CSV</button>
                    </form>
                </div>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>ID Order</th>
                            <th>Nama Pemesan</th>
                            <th>Metode Pembayaran</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        while ($lap = $query_laporan->fetch_assoc()): 
                            $grand_total += $lap['total_harga'];
                        ?>
                        <tr>
                            <td>#<?php echo $lap['id']; ?></td>
                            <td><?php echo htmlspecialchars($lap['nama_pemesan']); ?></td>
                            <td><?php echo htmlspecialchars($lap['metode_pembayaran'] ?? '-'); ?></td>
                            <td>Rp <?php echo number_format($lap['total_harga'], 0, ',', '.'); ?></td>
                            <td><?php echo $lap['status_pembayaran'] ?? 'Belum Dibayar'; ?></td>
                            <td><?php echo date("d M Y H:i", strtotime($lap['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>     