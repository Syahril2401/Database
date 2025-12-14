<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Cek username sudah ada
        $query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Username atau email sudah terdaftar!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $query = "INSERT INTO users (username, email, password, full_name, phone, address) 
                     VALUES (:username, :email, :password, :full_name, :phone, :address)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            
            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Swalayan Online</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .auth-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .text-center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>📝 Register Akun Baru</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label>No. Telepon</label>
                <input type="text" name="phone">
            </div>
            
            <div class="form-group">
                <label>Alamat</label>
                <textarea name="address" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Password * (min. 6 karakter)</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Konfirmasi Password *</label>
                <input type="password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
        </form>
        
        <div class="text-center">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
    </div>
</body>
</html>