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

// Ambil cart user
$query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id";
$stmt_cart = $conn->prepare($query_cart);
$stmt_cart->bindParam(':user_id', $user_id);
$stmt_cart->execute();

$cart_items = [];
$total = 0;

if ($stmt_cart->rowCount() > 0) {
    $cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
    $cart_id = $cart['cart_id'];

    // Ambil items di cart
    $query_items = "SELECT ci.*, p.product_name, p.price, p.image_url, i.stock_quantity 
                    FROM cart_items ci 
                    JOIN products p ON ci.product_id = p.product_id 
                    LEFT JOIN inventory i ON p.product_id = i.product_id 
                    WHERE ci.cart_id = :cart_id";
    $stmt_items = $conn->prepare($query_items);
    $stmt_items->bindParam(':cart_id', $cart_id);
    $stmt_items->execute();
    $cart_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Hitung total
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Alfaduro</title>
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

        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .cart-items {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }

        .item-details h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .item-price {
            color: #27ae60;
            font-weight: bold;
            font-size: 18px;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .cart-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            border-bottom: none;
            margin-top: 10px;
            color: #27ae60;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }

            .cart-item {
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
        <h2>🛒 Keranjang Belanja</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (count($cart_items) > 0): ?>
            <div class="cart-container">
                <!-- Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">📦</div>

                            <div class="item-details">
                                <h3><?php echo $item['product_name']; ?></h3>
                                <div class="item-price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></div>
                                <small style="color: #7f8c8d;">Stok tersedia: <?php echo $item['stock_quantity']; ?></small>
                            </div>

                            <div class="item-actions">
                                <form method="POST" action="cart_process.php" class="quantity-control">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                                        min="1" max="<?php echo $item['stock_quantity']; ?>">
                                    <button type="submit" name="update_quantity" class="btn btn-success">Update</button>
                                </form>

                                <div style="font-weight: bold; color: #2c3e50;">
                                    Subtotal: Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                </div>

                                <a href="cart_process.php?remove=<?php echo $item['cart_item_id']; ?>"
                                    class="btn btn-danger"
                                    onclick="return confirm('Hapus item ini dari keranjang?')">
                                    🗑️ Hapus
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 20px;">
                        <a href="cart_process.php?clear=1" class="btn btn-danger"
                            onclick="return confirm('Kosongkan semua keranjang?')">
                            🗑️ Kosongkan Keranjang
                        </a>
                    </div>
                </div>

                <!-- Summary -->
                <div class="cart-summary">
                    <h3>Ringkasan Belanja</h3>
                    <div class="summary-row">
                        <span>Total Item:</span>
                        <span><?php echo count($cart_items); ?> item</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Bayar:</span>
                        <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-success" style="width: 100%; margin-top: 20px; text-align: center;">
                        💳 Checkout
                    </a>

                    <a href="index.php" class="btn btn-primary" style="width: 100%; margin-top: 10px; text-align: center;">
                        ← Lanjut Belanja
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Keranjang Belanja Kosong</h3>
                    <p>Belum ada produk di keranjang Anda</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">
                        🛍️ Mulai Belanja
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>