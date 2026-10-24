<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// 1. TAMBAH PRODUK BARU
if (isset($_POST['tambah_produk'])) {
    $nama_menu = trim($_POST['nama_menu']);
    $kategori  = trim($_POST['kategori']);
    $harga     = (int)$_POST['harga'];
    $deskripsi = trim($_POST['deskripsi']);
    $gambar    = '';

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $img_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['gambar']['name']);
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], 'images/' . $img_name)) {
            $gambar = $img_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO menu (nama_menu, kategori, harga, gambar, deskripsi) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $nama_menu, $kategori, $harga, $gambar, $deskripsi);
    if ($stmt->execute()) {
        $message = "Produk berhasil ditambahkan!";
        $message_type = "success";
    }
}

// 2. EDIT PRODUK VIA POPUP
if (isset($_POST['update_produk'])) {
    $id        = (int)$_POST['id_menu'];
    $nama_menu = trim($_POST['nama_menu']);
    $kategori  = trim($_POST['kategori']);
    $harga     = (int)$_POST['harga'];
    $deskripsi = trim($_POST['deskripsi']);

    $res = $conn->query("SELECT gambar FROM menu WHERE id = $id");
    $row_old = $res->fetch_assoc();
    $gambar = $row_old['gambar'] ?? '';

    if (isset($_FILES['gambar_baru']) && $_FILES['gambar_baru']['error'] === UPLOAD_ERR_OK) {
        $img_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['gambar_baru']['name']);
        if (move_uploaded_file($_FILES['gambar_baru']['tmp_name'], 'images/' . $img_name)) {
            $gambar = $img_name;
        }
    }

    $stmt = $conn->prepare("UPDATE menu SET nama_menu=?, kategori=?, harga=?, gambar=?, deskripsi=? WHERE id=?");
    $stmt->bind_param("ssissi", $nama_menu, $kategori, $harga, $gambar, $deskripsi, $id);
    if ($stmt->execute()) {
        $message = "Produk berhasil diperbarui!";
        $message_type = "success";
    }
}

// 3. HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $conn->query("DELETE FROM menu WHERE id = $id");
    header("Location: produk.php?status=deleted");
    exit();
}

$result_menu = $conn->query("SELECT * FROM menu ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Produk - CARPERIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #d97706; --bg: #f8fafc; --text: #1e293b; --muted: #64748b; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; }
        body { background: #f1f5f9; color: var(--text); padding: 25px 15px; }
        .container { max-width: 1240px; margin: 0 auto; }
        .admin-nav { background: white; padding: 18px 25px; border-radius: 14px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .brand { font-size: 20px; font-weight: 700; color: var(--primary); }
        .nav-links a { text-decoration: none; color: var(--muted); font-weight: 500; font-size: 14px; margin: 0 6px; padding: 8px 16px; border-radius: 8px; }
        .nav-links a.active { color: var(--primary); background: #fef3c7; }
        .grid-layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; }
        .card { background: white; padding: 22px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        .btn-primary { width:100%; background: var(--primary); color: white; padding: 10px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 12px; font-size: 13px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; color: var(--muted); text-transform: uppercase; font-size: 11px; }
        
        /* Gambar Tabel Dibuat Lebih Besar */
        .img-thumb { width: 65px; height: 65px; border-radius: 10px; object-fit: cover; cursor: pointer; border: 2px solid #e2e8f0; transition: transform 0.2s, border-color 0.2s; }
        .img-thumb:hover { transform: scale(1.08); border-color: var(--primary); }
        
        .price-tag { font-weight: 600; color: #059669; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; }
        .btn-edit { background: #3b82f6; color: white; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; }
        .btn-delete { background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; }
        
        /* Modal Style */
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 999; }
        .modal-content { background: white; padding: 25px; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        
        /* Modal Pop-up Gambar Besar */
        .modal-img-content { background: white; padding: 20px; border-radius: 16px; width: 90%; max-width: 550px; text-align: center; position: relative; }
        .img-preview-large { width: 100%; max-height: 480px; object-fit: contain; border-radius: 12px; background: #f8fafc; padding: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-nav">
        <div class="brand">CARPERIN Admin</div>
        <div class="nav-links">
            <a href="admin.php">Pesanan</a>
            <a href="produk.php" class="active">Menu Produk</a>
            <a href="laporan.php">Laporan</a>
            <a href="logout.php" style="color:#ef4444;">Logout</a>
        </div>
    </div>

    <div class="grid-layout">
        <div class="card">
            <h3>Tambah Menu Baru</h3><br>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>Nama Menu</label><input type="text" name="nama_menu" required></div>
                <div class="form-group"><label>Kategori</label><input type="text" name="kategori" placeholder="Coffee / Alpukat Kocok" required></div>
                <div class="form-group"><label>Harga (Rp)</label><input type="number" name="harga" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" rows="2"></textarea></div>
                <div class="form-group"><label>Gambar (Unggah ke folder images)</label><input type="file" name="gambar"></div>
                <button type="submit" name="tambah_produk" class="btn-primary">Simpan Menu</button>
            </form>
        </div>

        <div class="card">
            <h3>Daftar Menu CARPERIN</h3><br>
            <table>
                <thead>
                    <tr><th>Gambar</th><th>Nama Menu</th><th>Kategori</th><th>Harga</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $result_menu->fetch_assoc()): ?>
                        <?php 
                            $gambar_file = $row['gambar'];
                            if (!empty($gambar_file) && file_exists('images/' . $gambar_file)) {
                                $src = 'images/' . $gambar_file;
                            } else {
                                $src = 'https://via.placeholder.com/300?text=No+Image';
                            }
                        ?>
                        <tr>
                            <td><img src="<?= $src ?>" class="img-thumb" onclick="openImage('<?= $src ?>', '<?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?>')" title="Klik untuk memperbesar"></td>
                            <td><strong><?= htmlspecialchars($row['nama_menu']) ?></strong></td>
                            <td><span style="color:var(--muted); font-size:12px;"><?= htmlspecialchars($row['kategori']) ?></span></td>
                            <td><span class="price-tag">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span></td>
                            <td>
                                <button class="btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                                <a href="produk.php?hapus=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Hapus menu ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edit Pop-up -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Menu Produk</h3><br>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_menu" id="edit_id">
            <div class="form-group"><label>Nama Menu</label><input type="text" name="nama_menu" id="edit_nama" required></div>
            <div class="form-group"><label>Kategori</label><input type="text" name="kategori" id="edit_kategori" required></div>
            <div class="form-group"><label>Harga (Rp)</label><input type="number" name="harga" id="edit_harga" required></div>
            <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi" id="edit_deskripsi" rows="2"></textarea></div>
            <div class="form-group"><label>Ganti Gambar Baru</label><input type="file" name="gambar_baru"></div>
            <div style="display:flex; gap:10px;">
                <button type="submit" name="update_produk" class="btn-primary">Update</button>
                <button type="button" class="btn-primary" style="background:#64748b;" onclick="closeModal('editModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Image Pop-up (Tampilan Besar & Jelas) -->
<div id="imgModal" class="modal" onclick="closeModal('imgModal')">
    <div class="modal-img-content" onclick="event.stopPropagation()">
        <h3 id="imgTitle" style="margin-bottom: 12px; color: var(--text);"></h3>
        <img id="imgPreview" src="" class="img-preview-large"><br><br>
        <button type="button" class="btn-primary" style="background: var(--primary);" onclick="closeModal('imgModal')">Tutup Gambar</button>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nama').value = data.nama_menu;
    document.getElementById('edit_kategori').value = data.kategori;
    document.getElementById('edit_harga').value = data.harga;
    document.getElementById('edit_deskripsi').value = data.deskripsi;
    document.getElementById('editModal').style.display = 'flex';
}

function openImage(src, title) {
    document.getElementById('imgTitle').innerText = title;
    document.getElementById('imgPreview').src = src;
    document.getElementById('imgModal').style.display = 'flex';
}

function closeModal(id) { 
    document.getElementById(id).style.display = 'none'; 
}
</script>
</body>
</html>