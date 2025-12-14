<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: orders.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Ambil detail order
$query = "SELECT o.*, p.payment_method, p.payment_status, p.payment_date,
          s.shipping_address, s.shipping_method, s.tracking_number, s.shipped_date, s.delivered_date
          FROM orders o 
          LEFT JOIN payments p ON o.order_id = p.order_id 
          LEFT JOIN shipping s ON o.order_id = s.order_id 
          WHERE o.order_id = :order_id AND o.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header("Location: orders.php");
    exit();
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil order items
$query_items = "SELECT oi.*, p.product_name, p.image_url 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = :order_id";
$stmt_items = $conn->prepare($query_items);
$stmt_items->bindParam(':order_id', $order_id);
$stmt_items->execute();
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order_id; ?> - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-container {
            max-width: 900px;
            margin: 30px auto;
        }

        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .info-box {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-box h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 14px;
        }

        .info-box p {
            margin: 5px 0;
            color: #555;
        }

        .item-list {
            margin: 20px 0;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
            margin: 20px 0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -32px;
            top: 0;
            width: 16px;
            height: 16px;
            background: #3498db;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #3498db;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -25px;
            top: 20px;
            width: 2px;
            height: calc(100% - 10px);
            background: #ddd;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-item.completed::before {
            background: #27ae60;
            box-shadow: 0 0 0 2px #27ae60;
        }

        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }

        .total-row.grand {
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
            border-top: 2px solid #ddd;
            padding-top: 15px;
            margin-top: 10px;
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
                <li><a href="../auth/logout.php" onclick="return confirm('Yakin ingin logout')">Logout (<?php echo $_SESSION['full_name']; ?>)</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="detail-container">
            <?php if ($message): ?>
                <div style="padding: 12px; border-radius: 5px; margin-bottom: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <a href="orders.php" class="btn btn-primary" style="margin-bottom: 20px;">← Kembali ke Pesanan</a>

            <div class="detail-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h2 style="margin: 0;">Order #<?php echo $order['order_id']; ?></h2>
                        <p style="color: #7f8c8d; margin: 5px 0;">
                            📅 <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?>
                        </p>
                    </div>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </div>

                <!-- Status Timeline -->
                <h3>📍 Status Pengiriman</h3>
                <div class="timeline">
                    <div class="timeline-item completed">
                        <strong>Pesanan Dibuat</strong>
                        <p style="color: #7f8c8d; font-size: 14px;">
                            <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?>
                        </p>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <strong>Sedang Diproses</strong>
                        <?php if ($order['status'] != 'pending'): ?>
                            <p style="color: #7f8c8d; font-size: 14px;">Pesanan sedang diproses</p>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <strong>Dalam Pengiriman</strong>
                        <?php if ($order['shipped_date']): ?>
                            <p style="color: #7f8c8d; font-size: 14px;">
                                <?php echo date('d M Y, H:i', strtotime($order['shipped_date'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($order['tracking_number']): ?>
                            <p style="color: #3498db; font-size: 14px;">
                                📦 Resi: <?php echo $order['tracking_number']; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-item <?php echo $order['status'] == 'delivered' ? 'completed' : ''; ?>">
                        <strong>Pesanan Diterima</strong>
                        <?php if ($order['delivered_date']): ?>
                            <p style="color: #7f8c8d; font-size: 14px;">
                                <?php echo date('d M Y, H:i', strtotime($order['delivered_date'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-box">
                    <h4>💳 Pembayaran</h4>
                    <p><strong>Metode:</strong> <?php echo strtoupper($order['payment_method']); ?></p>
                    <p><strong>Status:</strong>
                        <?php
                        if ($order['payment_status'] == 'paid') {
                            echo '<span style="color: #27ae60;">✅ LUNAS</span>';
                        } elseif ($order['payment_status'] == 'pending') {
                            echo '<span style="color: #f39c12;">⏳ BELUM DIBAYAR</span>';
                        } else {
                            echo '<span style="color: #e74c3c;">❌ GAGAL</span>';
                        }
                        ?>
                    </p>
                    <?php if ($order['payment_date']): ?>
                        <p><strong>Dibayar:</strong> <?php echo date('d M Y', strtotime($order['payment_date'])); ?></p>
                    <?php endif; ?>
                </div>

                <div class="info-box">
                    <h4>🚚 Pengiriman</h4>
                    <p><strong>Metode:</strong> <?php echo $order['shipping_method']; ?></p>
                    <p><strong>Alamat:</strong><br><?php echo nl2br($order['shipping_address']); ?></p>
                </div>
            </div>

            <!-- Order Items -->
            <div class="detail-card">
                <h3>📦 Item Pesanan</h3>
                <div class="item-list">
                    <?php foreach ($order_items as $item): ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-image">📦</div>
                                <div>
                                    <strong><?php echo $item['product_name']; ?></strong><br>
                                    <small style="color: #7f8c8d;">
                                        <?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                                    </small>
                                </div>
                            </div>
                            <div>
                                <strong style="color: #27ae60;">
                                    Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                </strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="total-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Ongkir:</span>
                        <span>FREE</span>
                    </div>
                    <div class="total-row grand">
                        <span>Total Bayar:</span>
                        <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($order['payment_method'] == 'transfer' && $order['payment_status'] == 'pending'): ?>
                <div class="detail-card" style="background: #fff3cd; border-left: 4px solid #f39c12;">
                    <h3>⚠️ Instruksi Pembayaran</h3>
                    <p>Silakan transfer ke rekening:</p>
                    <p style="font-size: 18px; margin: 10px 0;">
                        <strong>BCA 1234567890</strong><br>
                        <strong>a.n. Alfaduro</strong>
                    </p>
                    <p style="font-size: 20px; color: #27ae60; font-weight: bold;">
                        Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>