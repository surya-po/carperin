<?php
require_once 'koneksi.php';

$error = '';
if (isset($_POST['login'])) {
    $nama     = $conn->real_escape_string($_POST['nama']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM admins WHERE nama='$nama'");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if ($password === $admin['password'] || password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged']    = true;
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user']      = $admin['nama'];
            header("Location: admin.php");
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Nama admin tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin - Carperin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 340px; }
        .login-card h2 { margin-bottom: 20px; text-align: center; color: #0f172a; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #475569; font-weight: 600; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 5px; }
        .btn-login { width: 100%; padding: 10px; background: #0284c7; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-login:hover { background: #0369a1; }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 15px; text-align: center; background: #fee2e2; padding: 8px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Admin Carperin</h2>
        <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Nama Admin</label>
                <input type="text" name="nama" placeholder="Contoh: Serli" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn-login">Masuk</button>
        </form>
    </div>
</body>
</html>