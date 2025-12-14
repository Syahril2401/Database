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
$query = "SELECT o.*, p.payment_method, p.payment_status, p.payment_id
          FROM orders o 
          LEFT JOIN payments p ON o.order_id = p.order_id 
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

// Proses konfirmasi pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $payment_id = $order['payment_id'];

    // Update payment status menjadi paid
    $query_update = "UPDATE payments SET payment_status = 'paid', payment_date = NOW() 
                     WHERE payment_id = :payment_id";
    $stmt_update = $conn->prepare($query_update);
    $stmt_update->bindParam(':payment_id', $payment_id);

    if ($stmt_update->execute()) {
        // Update order status menjadi processing
        $query_order = "UPDATE orders SET status = 'processing' WHERE order_id = :order_id";
        $stmt_order = $conn->prepare($query_order);
        $stmt_order->bindParam(':order_id', $order_id);
        $stmt_order->execute();

        $_SESSION['message'] = "Pembayaran berhasil dikonfirmasi! Pesanan Anda sedang diproses.";
        header("Location: order_detail.php?order_id=" . $order_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .payment-icon {
            text-align: center;
            font-size: 60px;
            margin-bottom: 20px;
        }

        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .info-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
            margin-top: 10px;
        }

        .bank-info {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .bank-info h3 {
            margin-top: 0;
            color: #856404;
        }

        .bank-account {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 15px 0;
        }

        .instructions {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .instructions ol {
            margin: 10px 0 10px 20px;
        }

        .instructions li {
            margin: 8px 0;
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
                <li><a href="../auth/logout.php" onclick="return confirm('Yakin ingin logout')">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </ul>
        </div>
    </nav>

    <div class="payment-container">
        <div class="payment-icon">💳</div>
        <h2 style="text-align: center; margin-bottom: 10px;">Pembayaran Order #<?php echo $order['order_id']; ?></h2>
        <p style="text-align: center; color: #7f8c8d;">Metode: <?php echo strtoupper($order['payment_method']); ?></p>

        <div class="payment-info">
            <div class="info-row">
                <span>Nomor Order:</span>
                <span><strong>#<?php echo $order['order_id']; ?></strong></span>
            </div>
            <div class="info-row">
                <span>Status Order:</span>
                <span><strong><?php echo strtoupper($order['status']); ?></strong></span>
            </div>
            <div class="info-row">
                <span>Total Bayar:</span>
                <span><strong style="color: #27ae60; font-size: 24px;">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></span>
            </div>
        </div>

        <?php if ($order['payment_method'] == 'transfer'): ?>
            <div class="bank-info">
                <h3>📱 Transfer ke Rekening:</h3>
                <div class="bank-account">
                    BCA 1234567890<br>
                    <span style="font-size: 18px;">a.n. Alfaduro Store</span>
                </div>
                <p style="margin: 0; color: #856404;">
                    <strong>Jumlah Transfer:</strong><br>
                    <span style="font-size: 20px; color: #27ae60;">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                </p>
            </div>

            <div class="instructions">
                <h3 style="margin-top: 0; color: #0c5460;">📝 Instruksi Pembayaran:</h3>
                <ol>
                    <li>Transfer sesuai nominal yang tertera ke rekening di atas</li>
                    <li>Simpan bukti transfer Anda</li>
                    <li>Klik tombol "Konfirmasi Pembayaran" di bawah ini</li>
                    <li>Pesanan akan diproses setelah pembayaran dikonfirmasi</li>
                </ol>
            </div>

        <?php elseif ($order['payment_method'] == 'e-wallet'): ?>
            <div class="bank-info">
                <h3>📱 Pembayaran E-Wallet:</h3>
                <p>Scan QR Code berikut untuk membayar:</p>
                <div style="text-align: center; margin: 20px 0;">
                    <div style="width: 200px; height: 200px; background: #f0f0f0; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 48px;">
                        📱
                    </div>
                    <p style="margin-top: 10px; color: #666;">QR Code untuk GoPay / OVO / DANA</p>
                </div>
            </div>

            <div class="instructions">
                <h3 style="margin-top: 0; color: #0c5460;">📝 Instruksi Pembayaran:</h3>
                <ol>
                    <li>Buka aplikasi GoPay / OVO / DANA</li>
                    <li>Scan QR Code di atas</li>
                    <li>Konfirmasi pembayaran di aplikasi</li>
                    <li>Klik tombol "Konfirmasi Pembayaran" setelah berhasil bayar</li>
                </ol>
            </div>

        <?php else: // COD 
        ?>
            <div class="bank-info">
                <h3>💵 Cash on Delivery (COD):</h3>
                <p>Anda memilih metode pembayaran <strong>tunai saat barang diterima</strong>.</p>
                <p style="margin-top: 15px;">Siapkan uang pas:</p>
                <div class="bank-account">
                    Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                </div>
            </div>

            <div class="instructions">
                <h3 style="margin-top: 0; color: #0c5460;">📝 Instruksi:</h3>
                <ol>
                    <li>Pesanan Anda akan segera diproses</li>
                    <li>Siapkan uang pas saat kurir tiba</li>
                    <li>Bayar langsung kepada kurir saat menerima paket</li>
                    <li>Klik "Konfirmasi" untuk melanjutkan</li>
                </ol>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <button type="submit" name="confirm_payment" class="btn btn-success"
                style="width: 100%; padding: 15px; font-size: 18px; margin-top: 20px;"
                onclick="return confirm('Apakah Anda sudah melakukan pembayaran?')">
                ✅ Konfirmasi Pembayaran
            </button>
        </form>

        <a href="order_detail.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary"
            style="width: 100%; text-align: center; margin-top: 10px; display: block;">
            ← Kembali ke Detail Order
        </a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>