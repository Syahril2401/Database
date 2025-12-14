<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Ambil semua pesanan user
$query = "SELECT o.*, p.payment_method, p.payment_status 
          FROM orders o 
          LEFT JOIN payments p ON o.order_id = p.order_id 
          WHERE o.user_id = :user_id 
          ORDER BY o.order_date DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .orders-container {
            max-width: 1000px;
            margin: 30px auto;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            margin-bottom: 15px;
        }

        .order-number {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }

        .order-date {
            color: #7f8c8d;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: bold;
            color: #2c3e50;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <nav>
        <div class="container">
            <h1>🏪 Alfaduro - Swalayan Online</h1>
            <ul>
                <li><a href="index.php">Beranda</a></li>
                <?php
                // Hitung jumlah item di cart
                $cart_count = 0;
                $query_cart_count = "SELECT SUM(ci.quantity) as total 
                     FROM carts c 
                     LEFT JOIN cart_items ci ON c.cart_id = ci.cart_id 
                     WHERE c.user_id = :user_id";
                $stmt_cart_count = $conn->prepare($query_cart_count);
                $stmt_cart_count->bindParam(':user_id', $_SESSION['user_id']);
                $stmt_cart_count->execute();
                $cart_result = $stmt_cart_count->fetch(PDO::FETCH_ASSOC);
                $cart_count = $cart_result['total'] ?? 0;
                ?>
                <li>
                    <a href="cart.php">
                        🛒 Keranjang
                        <?php if ($cart_count > 0): ?>
                            <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px;">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="orders.php">📦 Pesanan Saya</a></li>
                <li><a href="../auth/logout.php" onclick="return confirm('Yakin ingin logout')">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="orders-container">
            <h2>📦 Pesanan Saya</h2>

            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number">Order #<?php echo $order['order_id']; ?></div>
                                <div class="order-date">
                                    📅 <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>

                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">Total Pembayaran</span>
                                <span class="info-value" style="color: #27ae60; font-size: 18px;">
                                    Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                                </span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Metode Pembayaran</span>
                                <span class="info-value"><?php echo strtoupper($order['payment_method']); ?></span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Status Pembayaran</span>
                                <span class="info-value">
                                    <?php
                                    if ($order['payment_status'] == 'paid') {
                                        echo '✅ LUNAS';
                                    } elseif ($order['payment_status'] == 'pending') {
                                        echo '⏳ BELUM DIBAYAR';
                                    } else {
                                        echo '❌ GAGAL';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="order-actions">
                            <a href="order_detail.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                                👁️ Lihat Detail
                            </a>

                            <?php if ($order['status'] == 'pending' && $order['payment_status'] == 'pending'): ?>
                                <a href="payment.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-success">💳 Bayar Sekarang</a>
                            <?php endif; ?>

                            <?php if ($order['status'] == 'delivered'): ?>
                                <a href="#" class="btn btn-success">⭐ Beri Review</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-orders">
                    <div class="empty-icon">📦</div>
                    <h3>Belum Ada Pesanan</h3>
                    <p>Anda belum memiliki riwayat pesanan</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">
                        🛍️ Mulai Belanja
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>