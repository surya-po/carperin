<?php
// 1. KONEKSI DATABASE & AMBIL DATA PESANAN
// (Sesuaikan koneksi database dan query dengan project Anda)
include 'koneksi.php'; 

$id_order = $_GET['id'] ?? 17; // Ambil ID Order dari URL atau default 17

$query = mysqli_query($koneksi, "SELECT * FROM pesanan WHERE id_order = '$id_order'");
$pesanan = mysqli_fetch_assoc($query);

// Jika data tidak ditemukan
if (!$pesanan) {
    echo "Pesanan tidak ditemukan!";
    exit;
}

// 2. LOGIKA OTOMATIS STATUS PEMBAYARAN
// Jika kolom bukti_pembayaran ada nilainya (sudah diupload/disimpan)
if (!empty($pesanan['bukti_pembayaran'])) {
    $status_pembayaran = "Sudah Dibayar";
    $status_color = "#28a745"; // Warna hijau
} else {
    $status_pembayaran = "Belum Bayar";
    $status_color = "#ffc107"; // Warna oranye/kuning
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $pesanan['id_order']; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .card-detail {
            max-width: 550px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        .header-order {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .title-order {
            font-size: 20px;
            font-weight: bold;
            color: #d90429;
            margin: 0;
        }
        .btn-batal {
            background-color: #ffe5e5;
            color: #d90429;
            border: none;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 0;
            font-size: 14px;
            color: #495057;
        }
        .info-table td.label {
            font-weight: 600;
            width: 120px;
        }
        .divider {
            border-top: 1px solid #eeeeee;
            margin: 15px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #d90429;
            margin-top: 10px;
        }
        .status-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            font-size: 15px;
        }
        .status-badge {
            font-weight: bold;
            color: <?php echo $status_color; ?>;
        }
        /* Box Bukti Pembayaran */
        .bukti-box {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-top: 15px;
        }
        .bukti-header {
            color: #2e7d32;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .bukti-img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="card-detail">
    <!-- Header ID Pesanan & Tombol Batal -->
    <div class="header-order">
        <h2 class="title-order">Pesanan #<?php echo $pesanan['id_order']; ?></h2>
        <button class="btn-batal">Batal</button>
    </div>

    <!-- Informasi Konsumen -->
    <table class="info-table">
        <tr>
            <td class="label">Konsumen:</td>
            <td><?php echo htmlspecialchars($pesanan['nama_konsumen']); ?></td>
        </tr>
        <tr>
            <td class="label">Tanggal:</td>
            <td><?php echo date('d M Y H:i', strtotime($pesanan['tanggal'])); ?></td>
        </tr>
        <tr>
            <td class="label">Pembayaran:</td>
            <td><?php echo strtoupper($pesanan['metode_pembayaran']); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Rincian Total -->
    <div class="total-row">
        <span>Total Produk:</span>
        <span>Rp <?php echo number_format($pesanan['total_produk'], 0, ',', '.'); ?></span>
    </div>
    <div class="total-row">
        <span>Ongkir:</span>
        <span>Rp <?php echo number_format($pesanan['ongkir'], 0, ',', '.'); ?></span>
    </div>
    <div class="total-row grand-total">
        <span>Total Keseluruhan:</span>
        <span>Rp <?php echo number_format($pesanan['total_keseluruhan'], 0, ',', '.'); ?></span>
    </div>

    <div class="divider"></div>

    <!-- Status Pembayaran Otomatis -->
    <div class="status-container">
        <span><strong>Status Pembayaran:</strong></span>
        <span class="status-badge"><?php echo $status_pembayaran; ?></span>
    </div>

    <!-- Box Bukti Pembayaran -->
    <?php if (!empty($pesanan['bukti_pembayaran'])): ?>
        <div class="bukti-box">
            <div class="bukti-header">
                ✅ Bukti Pembayaran Berhasil Disimpan:
            </div>
            <img src="uploads/<?php echo $pesanan['bukti_pembayaran']; ?>" alt="Bukti Pembayaran" class="bukti-img">
        </div>
    <?php else: ?>
        <div class="bukti-box" style="background-color: #fff3cd; border-color: #ffeeba;">
            <div class="bukti-header" style="color: #856404;">
                ⚠️ Belum ada bukti pembayaran yang diunggah.
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>