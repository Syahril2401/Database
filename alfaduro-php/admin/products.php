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

// CREATE - Tambah Produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $category_id = $_POST['category_id'];
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $image_url = trim($_POST['image_url']);

    $query = "INSERT INTO products (category_id, product_name, description, price, image_url) 
              VALUES (:category_id, :product_name, :description, :price, :image_url)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':product_name', $product_name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':image_url', $image_url);

    if ($stmt->execute()) {
        $product_id = $conn->lastInsertId();

        // Insert ke inventory
        $query_inv = "INSERT INTO inventory (product_id, stock_quantity) VALUES (:product_id, :stock_quantity)";
        $stmt_inv = $conn->prepare($query_inv);
        $stmt_inv->bindParam(':product_id', $product_id);
        $stmt_inv->bindParam(':stock_quantity', $stock_quantity);
        $stmt_inv->execute();

        $message = "Produk berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan produk!";
    }
}

// UPDATE - Edit Produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $product_id = $_POST['product_id'];
    $category_id = $_POST['category_id'];
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $image_url = trim($_POST['image_url']);

    $query = "UPDATE products SET category_id=:category_id, product_name=:product_name, 
              description=:description, price=:price, image_url=:image_url 
              WHERE product_id=:product_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':product_name', $product_name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':image_url', $image_url);

    if ($stmt->execute()) {
        // Update inventory
        $query_inv = "UPDATE inventory SET stock_quantity=:stock_quantity WHERE product_id=:product_id";
        $stmt_inv = $conn->prepare($query_inv);
        $stmt_inv->bindParam(':product_id', $product_id);
        $stmt_inv->bindParam(':stock_quantity', $stock_quantity);
        $stmt_inv->execute();

        $message = "Produk berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate produk!";
    }
}

// DELETE - Hapus Produk
if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];

    // Hapus dari inventory dulu
    $query_inv = "DELETE FROM inventory WHERE product_id = :product_id";
    $stmt_inv = $conn->prepare($query_inv);
    $stmt_inv->bindParam(':product_id', $product_id);
    $stmt_inv->execute();

    // Hapus produk
    $query = "DELETE FROM products WHERE product_id = :product_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);

    if ($stmt->execute()) {
        $message = "Produk berhasil dihapus!";
    } else {
        $error = "Gagal menghapus produk!";
    }
}

// READ - Ambil semua produk dengan JOIN
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT p.*, c.category_name, COALESCE(i.stock_quantity, 0) as stock 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.category_id 
          LEFT JOIN inventory i ON p.product_id = i.product_id";

if ($search) {
    $query .= " WHERE p.product_name LIKE :search OR p.description LIKE :search";
}

$query .= " ORDER BY p.product_id ASC";

$stmt = $conn->prepare($query);

if ($search) {
    $search_param = "%{$search}%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil kategori untuk dropdown
$query_cat = "SELECT * FROM categories ORDER BY category_name";
$stmt_cat = $conn->prepare($query_cat);
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Untuk edit - ambil data produk yang akan diedit
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $query_edit = "SELECT p.*, COALESCE(i.stock_quantity, 0) as stock 
                   FROM products p 
                   LEFT JOIN inventory i ON p.product_id = i.product_id 
                   WHERE p.product_id = :product_id";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->bindParam(':product_id', $edit_id);
    $stmt_edit->execute();
    $edit_product = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Alfaduro</title>
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

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .action-buttons a {
            margin-right: 10px;
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
        <h2><?php echo $edit_product ? '✏️ Edit Produk' : '➕ Tambah Produk Baru'; ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Search Box -->
        <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <form method="GET" action="">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="🔍 Cari produk..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <?php if (isset($_GET['search'])): ?>
                        <a href="products.php" class="btn btn-danger">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Form Tambah/Edit Produk -->
        <div class="form-container">
            <form method="POST" action="">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Produk *</label>
                        <input type="text" name="product_name" value="<?php echo $edit_product['product_name'] ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Kategori *</label>
                        <select name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                    <?php echo ($edit_product && $edit_product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" rows="3"><?php echo $edit_product['description'] ?? ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Harga (Rp) *</label>
                        <input type="number" name="price" step="0.01" value="<?php echo $edit_product['price'] ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Stok *</label>
                        <input type="number" name="stock_quantity" value="<?php echo $edit_product['stock'] ?? '0'; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Gambar</label>
                    <input type="text" name="image_url" value="<?php echo $edit_product['image_url'] ?? ''; ?>" placeholder="contoh: produk1.jpg">
                </div>

                <div class="form-group">
                    <?php if ($edit_product): ?>
                        <button type="submit" name="edit_product" class="btn btn-success">💾 Update Produk</button>
                        <a href="products.php" class="btn btn-primary">❌ Batal</a>
                    <?php else: ?>
                        <button type="submit" name="add_product" class="btn btn-primary">➕ Tambah Produk</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Produk -->
        <h2>📦 Daftar Produk</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Gambar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['product_id']; ?></td>
                            <td><?php echo $product['product_name']; ?></td>
                            <td><?php echo $product['category_name']; ?></td>
                            <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><?php echo $product['image_url'] ?: '-'; ?></td>
                            <td class="action-buttons">
                                <a href="products.php?edit=<?php echo $product['product_id']; ?>" class="btn btn-success">✏️ Edit</a>
                                <a href="products.php?delete=<?php echo $product['product_id']; ?>"
                                    class="btn btn-danger"
                                    onclick="return confirm('Yakin ingin menghapus produk ini?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Belum ada produk</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>

</html>