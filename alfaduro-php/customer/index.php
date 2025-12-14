<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Ambil semua produk dengan stok
$query = "SELECT p.*, c.category_name, COALESCE(i.stock_quantity, 0) as stock 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          LEFT JOIN inventory i ON p.product_id = i.product_id 
          WHERE i.stock_quantity > 0
          ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil kategori untuk filter
$query_cat = "SELECT * FROM categories ORDER BY category_name";
$stmt_cat = $conn->prepare($query_cat);
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Filter by category
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
if ($filter_category) {
    $query = "SELECT p.*, c.category_name, COALESCE(i.stock_quantity, 0) as stock 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.category_id 
              LEFT JOIN inventory i ON p.product_id = i.product_id 
              WHERE p.category_id = :category_id AND i.stock_quantity > 0
              ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $filter_category);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Notifikasi
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
    <title>Belanja - Alfaduro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
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
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #3498db;
            color: white;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .product-content {
            padding: 20px;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .product-category {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 24px;
            color: #27ae60;
            font-weight: bold;
            margin: 10px 0;
        }

        .product-stock {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 15px;
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

    <div class="container">
        <h2>🛒 Belanja Produk</h2>
        <p>Selamat datang, <?php echo $_SESSION['full_name']; ?>! Silakan pilih produk yang Anda inginkan.</p>

        <!-- notifikasi -->
        <?php if ($message): ?>
            <div class="alert alert-success" style="padding: 12px; border-radius: 5px; margin-bottom: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="padding: 12px; border-radius: 5px; margin-bottom: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Kategori -->
        <div class="filter-section">
            <h3>Filter Kategori:</h3>
            <div class="filter-buttons">
                <a href="index.php" class="filter-btn <?php echo !$filter_category ? 'active' : ''; ?>">Semua</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?php echo $cat['category_id']; ?>"
                        class="filter-btn <?php echo $filter_category == $cat['category_id'] ? 'active' : ''; ?>">
                        <?php echo $cat['category_name']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid Produk -->
        <div class="product-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            📦
                        </div>
                        <div class="product-content">
                            <div class="product-name"><?php echo $product['product_name']; ?></div>
                            <div class="product-category">📁 <?php echo $product['category_name']; ?></div>
                            <div class="product-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                            <div class="product-stock">Stok: <?php echo $product['stock']; ?></div>
                            <form method="POST" action="cart_process.php" style="display: flex; gap: 10px; align-items: center;">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>"
                                    style="width: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                <button type="submit" name="add_to_cart" class="btn btn-primary" style="flex: 1;">
                                    🛒 Tambah
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Tidak ada produk yang tersedia saat ini.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>