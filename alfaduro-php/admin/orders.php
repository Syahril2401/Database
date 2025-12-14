<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

// UPDATE STATUS ORDER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $query = "UPDATE orders SET status = :status WHERE order_id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':order_id', $order_id);
    
    if ($stmt->execute()) {
        // Jika status shipped, update shipped_date
        if ($new_status == 'shipped') {
            $query_ship = "UPDATE shipping SET shipped_date = NOW() WHERE order_id = :order_id";
            $stmt_ship = $conn->prepare($query_ship);
            $stmt_ship->bindParam(':order_id', $order_id);
            $stmt_ship->execute();
        }
        
        // Jika status delivered, update delivered_date
        if ($new_status == 'delivered') {
            $query_deliver = "UPDATE shipping SET delivered_date = NOW() WHERE order_id = :order_id";
            $stmt_deliver = $conn->prepare($query_deliver);
            $stmt_deliver->bindParam(':order_id', $order_id);
            $stmt_deliver->execute();
        }
        
        $message = "Status pesanan berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate status!";
    }
}

// UPDATE TRACKING NUMBER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tracking'])) {
    $order_id = $_POST['order_id'];
    $tracking_number = trim($_POST['tracking_number']);
    
    $query = "UPDATE shipping SET tracking_number = :tracking_number WHERE order_id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':tracking_number', $tracking_number);
    $stmt->bindParam(':order_id', $order_id);
    
    if ($stmt->execute()) {
        $message = "Nomor resi berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate nomor resi!";
    }
}

// UPDATE PAYMENT STATUS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    $order_id = $_POST['order_id'];
    $payment_status = $_POST['payment_status'];
    
    $query = "UPDATE payments SET payment_status = :payment_status, payment_date = NOW() 
              WHERE order_id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':payment_status', $payment_status);
    $stmt->bindParam(':order_id', $order_id);
    
    if ($stmt->execute()) {
        $message = "Status pembayaran berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate status pembayaran!";
    }
}

// Filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Ambil semua orders dengan JOIN
$query = "SELECT o.*, u.full_name, u.email, u.phone, 
          p.payment_method, p.payment_status,
          s.shipping_address, s.shipping_method, s.tracking_number
          FROM orders o
          JOIN users u ON o.user_id = u.user_id
          LEFT JOIN payments p ON o.order_id = p.order_id
          LEFT JOIN shipping s ON o.order_id = s.order_id";

if ($filter_status) {
    $query .= " WHERE o.status = :status";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);

if ($filter_status) {
    $stmt->bindParam(':status', $filter_status);
}

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$stats = [
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

$query_stats = "SELECT status, COUNT(*) as total FROM orders GROUP BY status";
$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->execute();
$stats_data = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

foreach ($stats_data as $stat) {
    $stats[$stat['status']] = $stat['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Alfaduro</title>
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
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-3px);
        }
        .stat-box.pending { border-left: 4px solid #f39c12; }
        .stat-box.processing { border-left: 4px solid #3498db; }
        .stat-box.shipped { border-left: 4px solid #9b59b6; }
        .stat-box.delivered { border-left: 4px solid #27ae60; }
        .stat-box.cancelled { border-left: 4px solid #e74c3c; }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            margin-top: 5px;
            text-transform: uppercase;
            font-size: 12px;
        }
        .orders-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #e2d1f1; color: #6c3483; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .payment-paid { color: #27ae60; font-weight: bold; }
        .payment-pending { color: #f39c12; font-weight: bold; }
        .payment-failed { color: #e74c3c; font-weight: bold; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #3498db;
            background: white;
            color: #3498db;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #3498db;
            color: white;
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
        <h2>📦 Kelola Pesanan</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <a href="orders.php?status=pending" style="text-decoration: none;">
                <div class="stat-box pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">⏳ Pending</div>
                </div>
            </a>
            <a href="orders.php?status=processing" style="text-decoration: none;">
                <div class="stat-box processing">
                    <div class="stat-number"><?php echo $stats['processing']; ?></div>
                    <div class="stat-label">🔄 Processing</div>
                </div>
            </a>
            <a href="orders.php?status=shipped" style="text-decoration: none;">
                <div class="stat-box shipped">
                    <div class="stat-number"><?php echo $stats['shipped']; ?></div>
                    <div class="stat-label">🚚 Shipped</div>
                </div>
            </a>
            <a href="orders.php?status=delivered" style="text-decoration: none;">
                <div class="stat-box delivered">
                    <div class="stat-number"><?php echo $stats['delivered']; ?></div>
                    <div class="stat-label">✅ Delivered</div>
                </div>
            </a>
            <a href="orders.php?status=cancelled" style="text-decoration: none;">
                <div class="stat-box cancelled">
                    <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">❌ Cancelled</div>
                </div>
            </a>
        </div>

        <!-- Filter -->
        <div class="filter-buttons">
            <a href="orders.php" class="filter-btn <?php echo !$filter_status ? 'active' : ''; ?>">Semua</a>
            <a href="orders.php?status=pending" class="filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="orders.php?status=processing" class="filter-btn <?php echo $filter_status == 'processing' ? 'active' : ''; ?>">Processing</a>
            <a href="orders.php?status=shipped" class="filter-btn <?php echo $filter_status == 'shipped' ? 'active' : ''; ?>">Shipped</a>
            <a href="orders.php?status=delivered" class="filter-btn <?php echo $filter_status == 'delivered' ? 'active' : ''; ?>">Delivered</a>
            <a href="orders.php?status=cancelled" class="filter-btn <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>

        <!-- Orders Table -->
        <div class="orders-table">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Pembayaran</th>
                        <th>Status Order</th>
                        <th>Pengiriman</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                <td>
                                    <?php echo $order['full_name']; ?><br>
                                    <small style="color: #7f8c8d;"><?php echo $order['email']; ?></small>
                                </td>
                                <td><?php echo date('d M Y', strtotime($order['order_date'])); ?><br>
                                    <small><?php echo date('H:i', strtotime($order['order_date'])); ?></small>
                                </td>
                                <td><strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></td>
                                <td>
                                    <?php echo strtoupper($order['payment_method']); ?><br>
                                    <span class="payment-<?php echo $order['payment_status']; ?>">
                                        <?php 
                                        if ($order['payment_status'] == 'paid') echo '✅ PAID';
                                        elseif ($order['payment_status'] == 'pending') echo '⏳ PENDING';
                                        else echo '❌ FAILED';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $order['shipping_method']; ?><br>
                                    <?php if ($order['tracking_number']): ?>
                                        <small style="color: #3498db;">📦 <?php echo $order['tracking_number']; ?></small>
                                    <?php else: ?>
                                        <small style="color: #7f8c8d;">No resi belum ada</small>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button onclick="viewOrder(<?php echo $order['order_id']; ?>)" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                                        👁️ Detail
                                    </button>
                                    <button onclick="updateStatus(<?php echo $order['order_id']; ?>, '<?php echo $order['status']; ?>')" class="btn btn-success" style="font-size: 12px; padding: 5px 10px;">
                                        🔄 Status
                                    </button>
                                    <?php if ($order['payment_status'] == 'pending'): ?>
                                        <button onclick="updatePayment(<?php echo $order['order_id']; ?>)" class="btn btn-success" style="font-size: 12px; padding: 5px 10px;">
                                            💰 Payment
                                        </button>
                                    <?php endif; ?>
                                    <?php if (in_array($order['status'], ['processing', 'shipped'])): ?>
                                        <button onclick="updateTracking(<?php echo $order['order_id']; ?>, '<?php echo $order['tracking_number']; ?>')" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                                            📦 Resi
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Tidak ada pesanan<?php echo $filter_status ? ' dengan status ' . $filter_status : ''; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal View Order -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
            <div id="orderDetails"></div>
        </div>
    </div>

    <!-- Modal Update Status -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('statusModal')">&times;</span>
            <h2>Update Status Pesanan</h2>
            <form method="POST" action="">
                <input type="hidden" name="order_id" id="status_order_id">
                <div class="form-group">
                    <label>Status Baru:</label>
                    <select name="status" id="status_select" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Modal Update Payment -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('paymentModal')">&times;</span>
            <h2>Update Status Pembayaran</h2>
            <form method="POST" action="">
                <input type="hidden" name="order_id" id="payment_order_id">
                <div class="form-group">
                    <label>Status Pembayaran:</label>
                    <select name="payment_status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <button type="submit" name="update_payment" class="btn btn-success" style="width: 100%;">Update Pembayaran</button>
            </form>
        </div>
    </div>

    <!-- Modal Update Tracking -->
    <div id="trackingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('trackingModal')">&times;</span>
            <h2>Update Nomor Resi</h2>
            <form method="POST" action="">
                <input type="hidden" name="order_id" id="tracking_order_id">
                <div class="form-group">
                    <label>Nomor Resi / Tracking Number:</label>
                    <input type="text" name="tracking_number" id="tracking_number" placeholder="Contoh: JNE123456789" required>
                </div>
                <button type="submit" name="update_tracking" class="btn btn-success" style="width: 100%;">Update Resi</button>
            </form>
        </div>
    </div>

    <script>
        function viewOrder(orderId) {
            // Fetch order details via AJAX (simplified version)
            document.getElementById('viewModal').style.display = 'block';
            document.getElementById('orderDetails').innerHTML = '<p>Loading...</p>';
            
            // In production, you'd fetch actual data via AJAX
            setTimeout(() => {
                document.getElementById('orderDetails').innerHTML = 
                    '<h3>Detail Order #' + orderId + '</h3>' +
                    '<p>Fitur detail order akan ditampilkan di sini...</p>' +
                    '<a href="order_detail.php?order_id=' + orderId + '" class="btn btn-primary">Lihat Detail Lengkap</a>';
            }, 500);
        }

        function updateStatus(orderId, currentStatus) {
            document.getElementById('statusModal').style.display = 'block';
            document.getElementById('status_order_id').value = orderId;
            document.getElementById('status_select').value = currentStatus;
        }

        function updatePayment(orderId) {
            document.getElementById('paymentModal').style.display = 'block';
            document.getElementById('payment_order_id').value = orderId;
        }

        function updateTracking(orderId, currentTracking) {
            document.getElementById('trackingModal').style.display = 'block';
            document.getElementById('tracking_order_id').value = orderId;
            document.getElementById('tracking_number').value = currentTracking || '';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
