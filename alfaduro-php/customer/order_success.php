<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: index.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Ambil detail order
$query = "SELECT o.*, p.payment_method, p.payment_status, s.shipping_address, s.shipping_method 
          FROM orders o 
          LEFT JOIN payments p ON o.order_id = p.order_id 
          LEFT JOIN shipping s ON o.order_id = s.order_id 
          WHERE o.order_id = :order_id AND o.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header("Location: index.php");
    exit();
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil order items
$query_items = "SELECT oi.*, p.product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = :order_id";
$stmt_items = $conn->prepare($query_items);
$stmt_items->bindParam(':order_id', $order_id);
$stmt_items->execute();
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .success-container {
            max-width: 700px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .order-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .order-items {
            margin: 20px 0;
            text-align: left;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: white;
            margin-bottom: 5px;
            border-radius: 5px;
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

    <div class="success-container">
        <div class="success-icon">✅</div>
        <h2>Pesanan Berhasil Dibuat!</h2>
        <p>Terima kasih telah berbelanja di Alfaduro</p>

        <div class="order-info">
            <h3>Detail Pesanan</h3>
            <div class="info-row">
                <strong>Nomor Order:</strong>
                <span>#<?php echo $order['order_id']; ?></span>
            </div>
            <div class="info-row">
                <strong>Tanggal:</strong>
                <span><?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="info-row">
                <strong>Status:</strong>
                <span class="badge"><?php echo strtoupper($order['status']); ?></span>
            </div>
            <div class="info-row">
                <strong>Metode Pembayaran:</strong>
                <span><?php echo strtoupper($order['payment_method']); ?></span>
            </div>
            <div class="info-row">
                <strong>Pengiriman:</strong>
                <span><?php echo $order['shipping_method']; ?></span>
            </div>
            <div class="info-row">
                <strong>Alamat:</strong>
                <span><?php echo $order['shipping_address']; ?></span>
            </div>
        </div>

        <div class="order-items">
            <h3>Item Pesanan</h3>
            <?php foreach ($order_items as $item): ?>
                <div class="item-row">
                    <div>
                        <strong><?php echo $item['product_name']; ?></strong><br>
                        <small><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></small>
                    </div>
                    <div>
                        <strong>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-info">
            <div class="info-row" style="font-size: 20px; font-weight: bold; color: #27ae60;">
                <span>Total Bayar:</span>
                <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
            </div>
        </div>

        <?php if ($order['payment_method'] == 'transfer'): ?>
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <strong>⚠️ Instruksi Pembayaran:</strong><br>
                Silakan transfer ke rekening:<br>
                <strong>BCA 1234567890 a.n. Alfaduro</strong><br>
                Sejumlah: <strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong>
            </div>
        <?php endif; ?>

        <a href="orders.php" class="btn btn-primary" style="margin: 10px;">📦 Lihat Pesanan Saya</a>
        <a href="index.php" class="btn btn-success" style="margin: 10px;">🛍️ Lanjut Belanja</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>