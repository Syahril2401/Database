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

// Ambil data user
$query_user = "SELECT * FROM users WHERE user_id = :user_id";
$stmt_user = $conn->prepare($query_user);
$stmt_user->bindParam(':user_id', $user_id);
$stmt_user->execute();
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Ambil cart items
$query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id";
$stmt_cart = $conn->prepare($query_cart);
$stmt_cart->bindParam(':user_id', $user_id);
$stmt_cart->execute();

if ($stmt_cart->rowCount() == 0) {
    $_SESSION['error'] = "Keranjang belanja kosong!";
    header("Location: cart.php");
    exit();
}

$cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
$cart_id = $cart['cart_id'];

// Ambil items di cart
$query_items = "SELECT ci.*, p.product_name, p.price, i.stock_quantity 
                FROM cart_items ci 
                JOIN products p ON ci.product_id = p.product_id 
                LEFT JOIN inventory i ON p.product_id = i.product_id 
                WHERE ci.cart_id = :cart_id";
$stmt_items = $conn->prepare($query_items);
$stmt_items->bindParam(':cart_id', $cart_id);
$stmt_items->execute();
$cart_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

if (count($cart_items) == 0) {
    $_SESSION['error'] = "Keranjang belanja kosong!";
    header("Location: cart.php");
    exit();
}

// Hitung total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Proses checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $payment_method = $_POST['payment_method'];
    $shipping_method = $_POST['shipping_method'];

    if (empty($shipping_address)) {
        $error = "Alamat pengiriman harus diisi!";
    } else {
        // Mulai transaction
        $conn->beginTransaction();

        try {
            // 1. Buat order
            $query_order = "INSERT INTO orders (user_id, total_amount, status) 
                          VALUES (:user_id, :total_amount, 'pending')";
            $stmt_order = $conn->prepare($query_order);
            $stmt_order->bindParam(':user_id', $user_id);
            $stmt_order->bindParam(':total_amount', $total);
            $stmt_order->execute();
            $order_id = $conn->lastInsertId();

            // 2. Insert order items & kurangi stok
            foreach ($cart_items as $item) {
                // Insert order item
                $query_order_item = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (:order_id, :product_id, :quantity, :price)";
                $stmt_order_item = $conn->prepare($query_order_item);
                $stmt_order_item->bindParam(':order_id', $order_id);
                $stmt_order_item->bindParam(':product_id', $item['product_id']);
                $stmt_order_item->bindParam(':quantity', $item['quantity']);
                $stmt_order_item->bindParam(':price', $item['price']);
                $stmt_order_item->execute();

                // Kurangi stok
                $query_update_stock = "UPDATE inventory SET stock_quantity = stock_quantity - :quantity 
                                     WHERE product_id = :product_id";
                $stmt_update_stock = $conn->prepare($query_update_stock);
                $stmt_update_stock->bindParam(':quantity', $item['quantity']);
                $stmt_update_stock->bindParam(':product_id', $item['product_id']);
                $stmt_update_stock->execute();
            }

            // 3. Insert payment
            $query_payment = "INSERT INTO payments (order_id, payment_method, payment_status) 
                            VALUES (:order_id, :payment_method, 'pending')";
            $stmt_payment = $conn->prepare($query_payment);
            $stmt_payment->bindParam(':order_id', $order_id);
            $stmt_payment->bindParam(':payment_method', $payment_method);
            $stmt_payment->execute();

            // 4. Insert shipping
            $query_shipping = "INSERT INTO shipping (order_id, shipping_address, shipping_method) 
                             VALUES (:order_id, :shipping_address, :shipping_method)";
            $stmt_shipping = $conn->prepare($query_shipping);
            $stmt_shipping->bindParam(':order_id', $order_id);
            $stmt_shipping->bindParam(':shipping_address', $shipping_address);
            $stmt_shipping->bindParam(':shipping_method', $shipping_method);
            $stmt_shipping->execute();

            // 5. Hapus cart items
            $query_delete_cart = "DELETE FROM cart_items WHERE cart_id = :cart_id";
            $stmt_delete_cart = $conn->prepare($query_delete_cart);
            $stmt_delete_cart->bindParam(':cart_id', $cart_id);
            $stmt_delete_cart->execute();

            // Commit transaction
            $conn->commit();

            $_SESSION['message'] = "Pesanan berhasil dibuat! Nomor Order: #" . $order_id;
            header("Location: order_success.php?order_id=" . $order_id);
            exit();
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .checkout-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .checkout-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .order-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
            margin-top: 10px;
        }

        .alert-error {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .payment-options,
        .shipping-options {
            display: grid;
            gap: 10px;
        }

        .option-card {
            border: 2px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .option-card:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }

        .option-card input[type="radio"] {
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
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
        <h2>💳 Checkout</h2>

        <?php if (isset($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="checkout-container">
            <!-- Form Checkout -->
            <div class="checkout-form">
                <form method="POST" action="">
                    <!-- Informasi Pengiriman -->
                    <div class="form-section">
                        <h3>📦 Informasi Pengiriman</h3>

                        <div class="form-group">
                            <label>Nama Penerima *</label>
                            <input type="text" value="<?php echo $user['full_name']; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>No. Telepon *</label>
                            <input type="text" value="<?php echo $user['phone']; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Alamat Pengiriman *</label>
                            <textarea name="shipping_address" rows="4" required><?php echo $user['address']; ?></textarea>
                            <small style="color: #7f8c8d;">Pastikan alamat lengkap dan benar</small>
                        </div>

                        <div class="form-group">
                            <label>Metode Pengiriman *</label>
                            <div class="shipping-options">
                                <label class="option-card">
                                    <input type="radio" name="shipping_method" value="JNE Regular" checked required>
                                    <strong>JNE Regular</strong> - Estimasi 3-5 hari (FREE)
                                </label>
                                <label class="option-card">
                                    <input type="radio" name="shipping_method" value="JNE Express" required>
                                    <strong>JNE Express</strong> - Estimasi 1-2 hari (Rp 15.000)
                                </label>
                                <label class="option-card">
                                    <input type="radio" name="shipping_method" value="Ambil di Toko" required>
                                    <strong>Ambil di Toko</strong> - Gratis (Siap dalam 2 jam)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div class="form-section">
                        <h3>💰 Metode Pembayaran</h3>

                        <div class="payment-options">
                            <label class="option-card">
                                <input type="radio" name="payment_method" value="transfer" checked required>
                                <strong>Transfer Bank</strong><br>
                                <small>BCA / Mandiri / BNI</small>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="payment_method" value="e-wallet" required>
                                <strong>E-Wallet</strong><br>
                                <small>GoPay / OVO / DANA</small>
                            </label>
                            <label class="option-card">
                                <input type="radio" name="payment_method" value="cod" required>
                                <strong>COD (Cash on Delivery)</strong><br>
                                <small>Bayar saat barang diterima</small>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px;">
                        🛒 Buat Pesanan
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3>Ringkasan Pesanan</h3>

                <?php foreach ($cart_items as $item): ?>
                    <div class="summary-item">
                        <div>
                            <strong><?php echo $item['product_name']; ?></strong><br>
                            <small><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></small>
                        </div>
                        <div>
                            <strong>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-item">
                    <span>Subtotal:</span>
                    <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                </div>

                <div class="summary-item">
                    <span>Ongkir:</span>
                    <span>FREE</span>
                </div>

                <div class="summary-total">
                    <span>Total Bayar:</span>
                    <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                </div>

                <a href="cart.php" class="btn btn-primary" style="width: 100%; text-align: center; margin-top: 10px;">
                    ← Kembali ke Keranjang
                </a>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>