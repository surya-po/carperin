<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$total_pendapatan = $conn->query("SELECT SUM(total_harga) as total FROM orders")->fetch_assoc()['total'] ?? 0;
$total_transaksi  = $conn->query("SELECT COUNT(*) as jml FROM orders")->fetch_assoc()['jml'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan - CARPERIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #d97706; --muted: #64748b; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; }
        body { background: #f1f5f9; padding: 25px 15px; }
        .container { max-width: 1240px; margin: 0 auto; }
        .admin-nav { background: white; padding: 18px 25px; border-radius: 14px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .brand { font-size: 20px; font-weight: 700; color: var(--primary); }
        .nav-links a { text-decoration: none; color: var(--muted); font-weight: 500; font-size: 14px; margin: 0 6px; padding: 8px 16px; border-radius: 8px; }
        .nav-links a.active { color: var(--primary); background: #fef3c7; }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-val { font-size: 24px; font-weight: 700; color: var(--primary); margin-top: 5px; }
        .btn-report { background: #059669; color: white; padding: 10px 20px; border-radius: 8px; border:none; cursor:pointer; font-weight:600; }
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 14px; width: 100%; max-width: 400px; text-align:center; }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-nav">
        <div class="brand">CARPERIN Admin</div>
        <div class="nav-links">
            <a href="admin.php">Pesanan</a>
            <a href="produk.php">Menu Produk</a>
            <a href="laporan.php" class="active">Laporan</a>
            <a href="logout.php" style="color:#ef4444;">Logout</a>
        </div>
    </div>

    <div class="grid-stats">
        <div class="stat-card">
            <span style="color:var(--muted); font-size:13px;">Total Pendapatan</span>
            <div class="stat-val">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <span style="color:var(--muted); font-size:13px;">Total Transaksi Selesai</span>
            <div class="stat-val"><?= $total_transaksi ?> Transaksi</div>
        </div>
    </div>

    <button class="btn-report" onclick="document.getElementById('reportModal').style.display='flex'">Pop-up Cetak Ringkasan</button>
</div>

<div id="reportModal" class="modal">
    <div class="modal-content">
        <h3>Ringkasan Laporan Penjualan</h3><br>
        <p style="font-size:14px; color:#475569;">Total Omset: <strong>Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></strong></p>
        <p style="font-size:14px; color:#475569;">Jumlah Pesanan: <strong><?= $total_transaksi ?> Transaksi</strong></p><br>
        <button class="btn-report" style="width:100%;" onclick="window.print()">Cetak PDF / Print</button><br><br>
        <button class="btn-report" style="width:100%; background:#64748b;" onclick="document.getElementById('reportModal').style.display='none'">Tutup</button>
    </div>
</div>
</body>
</html>