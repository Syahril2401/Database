<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password wajib diisi!";
    } else {
        $query = "SELECT * FROM users WHERE username = :username OR email = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect sesuai role
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/index.php");
                } else {
                    header("Location: ../customer/index.php");
                }
                exit();
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Swalayan Online</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 100px auto;
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
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .text-center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>🔐 Login</h2>
        
        <?php if ($error): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Username atau Email</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        
        <div class="text-center">
            Belum punya akun? <a href="register.php">Register di sini</a>
        </div>
        
        <div class="text-center" style="margin-top: 10px; font-size: 12px; color: #666;">
            <p>Demo Account:</p>
            <p>Admin: admin / admin123</p>
            <p>Customer: customer / customer123</p>
        </div>
    </div>
</body>
</html>