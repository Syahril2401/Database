<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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

// Ambil detail order lengkap
$query = "SELECT o.*, u.full_name, u.email, u.phone, u.address,
          p.payment_method, p.payment_status, p.payment_date, p.payment_id,
          s.shipping_address, s.shipping_method, s.tracking_number, s.shipped_date, s.delivered_date, s.shipping_id
          FROM orders o 
          JOIN users u ON o.user_id = u.user_id
          LEFT JOIN payments p ON o.order_id = p.order_id 
          LEFT JOIN shipping s ON o.order_id = s.order_id 
          WHERE o.order_id = :order_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':order_id', $order_id);
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

// Proses update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'];
        
        $query_update = "UPDATE orders SET status = :status WHERE order_id = :order_id";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bindParam(':status', $new_status);
        $stmt_update->bindParam(':order_id', $order_id);
        
        if ($stmt_update->execute()) {
            if ($new_status == 'shipped' && $order['shipping_id']) {
                $query_ship = "UPDATE shipping SET shipped_date = NOW() WHERE shipping_id = :shipping_id";
                $stmt_ship = $conn->prepare($query_ship);
                $stmt_ship->bindParam(':shipping_id', $order['shipping_id']);
                $stmt_ship->execute();
            }
            
            if ($new_status == 'delivered' && $order['shipping_id']) {
                $query_deliver = "UPDATE shipping SET delivered_date = NOW() WHERE shipping_id = :shipping_id";
                $stmt_deliver = $conn->prepare($query_deliver);
                $stmt_deliver->bindParam(':shipping_id', $order['shipping_id']);
                $stmt_deliver->execute();
            }
            
            $message = "Status berhasil diupdate!";
            header("Refresh:0");
        }
    }
    
    if (isset($_POST['update_tracking'])) {
        $tracking = trim($_POST['tracking_number']);
        
        $query_track = "UPDATE shipping SET tracking_number = :tracking WHERE shipping_id = :shipping_id";
        $stmt_track = $conn->prepare($query_track);
        $stmt_track->bindParam(':tracking', $tracking);
        $stmt_track->bindParam(':shipping_id', $order['shipping_id']);
        
        if ($stmt_track->execute()) {
            $message = "Nomor resi berhasil diupdate!";
            header("Refresh:0");
        }
    }
    
    if (isset($_POST['update_payment'])) {
        $payment_status = $_POST['payment_status'];
        
        $query_pay = "UPDATE payments SET payment_status = :payment_status, payment_date = NOW() WHERE payment_id = :payment_id";
        $stmt_pay = $conn->prepare($query_pay);
        $stmt_pay->bindParam(':payment_status', $payment_status);
        $stmt_pay->bindParam(':payment_id', $order['payment_id']);
        
        if ($stmt_pay->execute()) {
            $message = "Status pembayaran berhasil diupdate!";
            header("Refresh:0");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Order #<?php echo $order_id; ?> - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .detail-container {
            max-width: 1200px;
            margin: 30px auto;
        }
        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #e2d1f1; color: #6c3483; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-box {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        .info-box h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
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
        .action-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .action-card h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
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
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="detail-container">
            <a href="orders.php" class="btn btn-primary" style="margin-bottom: 20px;">← Kembali ke Daftar Pesanan</a>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="detail-card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
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
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
                <!-- Customer Info -->
                <div class="info-box">
                    <h4>👤 Informasi Customer</h4>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Nama:</span>
                        <strong><?php echo $order['full_name']; ?></strong>
                    </div>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Email:</span>
                        <strong><?php echo $order['email']; ?></strong>
                    </div>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Telepon:</span>
                        <strong><?php echo $order['phone']; ?></strong>
                    </div>
                </div>
                
                <!-- Payment Info -->
                <div class="info-box" style="border-left-color: #27ae60;">
                    <h4>💳 Informasi Pembayaran</h4>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Metode:</span>
                        <strong><?php echo strtoupper($order['payment_method']); ?></strong>
                    </div>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Status:</span>
                        <strong style="color: <?php echo $order['payment_status'] == 'paid' ? '#27ae60' : '#f39c12'; ?>">
                            <?php echo strtoupper($order['payment_status']); ?>
                        </strong>
                    </div>
                    <?php if ($order['payment_date']): ?>
                        <div class="info-row">
                            <span style="color: #7f8c8d;">Dibayar:</span>
                            <strong><?php echo date('d M Y', strtotime($order['payment_date'])); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Shipping Info -->
                <div class="info-box" style="border-left-color: #9b59b6;">
                    <h4>🚚 Informasi Pengiriman</h4>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Metode:</span>
                        <strong><?php echo $order['shipping_method']; ?></strong>
                    </div>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">No. Resi:</span>
                        <strong><?php echo $order['tracking_number'] ?: 'Belum ada'; ?></strong>
                    </div>
                    <div class="info-row">
                        <span style="color: #7f8c8d;">Alamat:</span>
                        <strong style="text-align: right;"><?php echo nl2br($order['shipping_address']); ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="detail-card">
                <h3>📍 Status Timeline</h3>
                <div class="timeline">
                    <div class="timeline-item completed">
                        <strong>Pesanan Dibuat</strong>
                        <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0;">
                            <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?>
                        </p>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <strong>Sedang Diproses</strong>
                        <?php if ($order['status'] != 'pending'): ?>
                            <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0;">Pesanan sedang diproses</p>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-item <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                        <strong>Dalam Pengiriman</strong>
                        <?php if ($order['shipped_date']): ?>
                            <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0;">
                                <?php echo date('d M Y, H:i', strtotime($order['shipped_date'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-item <?php echo $order['status'] == 'delivered' ? 'completed' : ''; ?>">
                        <strong>Pesanan Diterima</strong>
                        <?php if ($order['delivered_date']): ?>
                            <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0;">
                                <?php echo date('d M Y, H:i', strtotime($order['delivered_date'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
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
                                <strong style="color: #27ae60; font-size: 18px;">
                                    Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                </strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;">
                            <span>Subtotal:</span>
                            <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;">
                            <span>Ongkir:</span>
                            <span>FREE</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 15px 0; font-size: 20px; font-weight: bold; color: #27ae60;">
                            <span>Total:</span>
                            <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Cards -->
            <div class="action-section">
                <!-- Update Status -->
                <div class="action-card">
                    <h3>🔄 Update Status Order</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Status Baru:</label>
                            <select name="status" class="form-control" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">Update Status</button>
                    </form>
                </div>
                
                <!-- Update Payment -->
                <div class="action-card">
                    <h3>💰 Update Pembayaran</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Status Pembayaran:</label>
                            <select name="payment_status" class="form-control" required>
                                <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <button type="submit" name="update_payment" class="btn btn-success" style="width: 100%;">Update Payment</button>
                    </form>
                </div>
                
                <!-- Update Tracking -->
                <div class="action-card">
                    <h3>📦 Update Nomor Resi</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Nomor Resi:</label>
                            <input type="text" name="tracking_number" class="form-control" 
                                   value="<?php echo $order['tracking_number']; ?>" 
                                   placeholder="Contoh: JNE123456789" required>
                        </div>
                        <button type="submit" name="update_tracking" class="btn btn-success" style="width: 100%;">Update Resi</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>