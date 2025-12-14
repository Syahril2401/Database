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

// CREATE - Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    $query = "INSERT INTO categories (category_name, description) VALUES (:category_name, :description)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_name', $category_name);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $message = "Kategori berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan kategori!";
    }
}

// UPDATE - Edit Kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $category_id = $_POST['category_id'];
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    $query = "UPDATE categories SET category_name=:category_name, description=:description WHERE category_id=:category_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':category_name', $category_name);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $message = "Kategori berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate kategori!";
    }
}

// DELETE - Hapus Kategori
if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // Cek apakah ada produk yang menggunakan kategori ini
    $query_check = "SELECT COUNT(*) as total FROM products WHERE category_id = :category_id";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bindParam(':category_id', $category_id);
    $stmt_check->execute();
    $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($check['total'] > 0) {
        $error = "Tidak bisa hapus! Masih ada " . $check['total'] . " produk yang menggunakan kategori ini.";
    } else {
        $query = "DELETE FROM categories WHERE category_id = :category_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        
        if ($stmt->execute()) {
            $message = "Kategori berhasil dihapus!";
        } else {
            $error = "Gagal menghapus kategori!";
        }
    }
}

// READ - Ambil semua kategori
$query = "SELECT c.*, COUNT(p.product_id) as total_products 
          FROM categories c 
          LEFT JOIN products p ON c.category_id = p.category_id 
          GROUP BY c.category_id 
          ORDER BY c.category_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Untuk edit
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $query_edit = "SELECT * FROM categories WHERE category_id = :category_id";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->bindParam(':category_id', $edit_id);
    $stmt_edit->execute();
    $edit_category = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Alfaduro</title>
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        <h2><?php echo $edit_category ? '✏️ Edit Kategori' : '➕ Tambah Kategori Baru'; ?></h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Form Tambah/Edit Kategori -->
        <div class="form-container">
            <form method="POST" action="">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Nama Kategori *</label>
                    <input type="text" name="category_name" value="<?php echo $edit_category['category_name'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" rows="3"><?php echo $edit_category['description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <?php if ($edit_category): ?>
                        <button type="submit" name="edit_category" class="btn btn-success">💾 Update Kategori</button>
                        <a href="categories.php" class="btn btn-primary">❌ Batal</a>
                    <?php else: ?>
                        <button type="submit" name="add_category" class="btn btn-primary">➕ Tambah Kategori</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Kategori -->
        <h2>📁 Daftar Kategori</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th>Total Produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categories) > 0): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['category_id']; ?></td>
                            <td><?php echo $category['category_name']; ?></td>
                            <td><?php echo $category['description'] ?: '-'; ?></td>
                            <td><?php echo $category['total_products']; ?> produk</td>
                            <td>
                                <a href="categories.php?edit=<?php echo $category['category_id']; ?>" class="btn btn-success">✏️ Edit</a>
                                <a href="categories.php?delete=<?php echo $category['category_id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Yakin ingin menghapus kategori ini?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Belum ada kategori</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>