<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Hitung statistik
$query_products = "SELECT COUNT(*) as total FROM products";
$stmt_products = $conn->prepare($query_products);
$stmt_products->execute();
$total_products = $stmt_products->fetch(PDO::FETCH_ASSOC)['total'];

$query_orders = "SELECT COUNT(*) as total FROM orders";
$stmt_orders = $conn->prepare($query_orders);
$stmt_orders->execute();
$total_orders = $stmt_orders->fetch(PDO::FETCH_ASSOC)['total'];

$query_users = "SELECT COUNT(*) as total FROM users WHERE role='customer'";
$stmt_users = $conn->prepare($query_users);
$stmt_users->execute();
$total_customers = $stmt_users->fetch(PDO::FETCH_ASSOC)['total'];

$query_revenue = "SELECT SUM(total_amount) as total FROM orders WHERE status='delivered'";
$stmt_revenue = $conn->prepare($query_revenue);
$stmt_revenue->execute();
$total_revenue = $stmt_revenue->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <h1>🏪 Alfaduro - Admin Panel</h1>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="products.php">Produk</a></li>
                <li><a href="categories.php">Kategori</a></li>
                <li><a href="orders.php">Pesanan</a></li>
                <li><a href="users.php">Pengguna</a></li>
                <li><a href="../auth/logout.php" onclick="return confirm('Yakin ingin logout')">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
        </div>
    </nav>

    <div class="container">
        <h2>Dashboard</h2>
        <p>Selamat datang, <?php echo $_SESSION['full_name']; ?>! 👋</p>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>📦 Total Produk</h3>
                <div class="number"><?php echo $total_products; ?></div>
            </div>
            <div class="stat-card">
                <h3>🛒 Total Pesanan</h3>
                <div class="number"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>👥 Total Pelanggan</h3>
                <div class="number"><?php echo $total_customers; ?></div>
            </div>
            <div class="stat-card">
                <h3>💰 Total Pendapatan</h3>
                <div class="number">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="card">
            <h3>🚀 Quick Actions</h3>
            <a href="products.php?action=add" class="btn btn-primary">Tambah Produk Baru</a>
            <a href="orders.php" class="btn btn-success">Lihat Pesanan</a>
            <a href="users.php" class="btn btn-primary">Kelola Pengguna</a>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>