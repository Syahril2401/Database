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

// DELETE USER
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];

    // Cek apakah user punya order
    $query_check = "SELECT COUNT(*) as total FROM orders WHERE user_id = :user_id";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();
    $check = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($check['total'] > 0) {
        $error = "Tidak bisa hapus! User ini memiliki " . $check['total'] . " pesanan.";
    } else {
        // Hapus cart items dulu
        $query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id";
        $stmt_cart = $conn->prepare($query_cart);
        $stmt_cart->bindParam(':user_id', $user_id);
        $stmt_cart->execute();

        if ($stmt_cart->rowCount() > 0) {
            $cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
            $cart_id = $cart['cart_id'];

            $query_del_items = "DELETE FROM cart_items WHERE cart_id = :cart_id";
            $stmt_del_items = $conn->prepare($query_del_items);
            $stmt_del_items->bindParam(':cart_id', $cart_id);
            $stmt_del_items->execute();

            $query_del_cart = "DELETE FROM carts WHERE cart_id = :cart_id";
            $stmt_del_cart = $conn->prepare($query_del_cart);
            $stmt_del_cart->bindParam(':cart_id', $cart_id);
            $stmt_del_cart->execute();
        }

        // Hapus user
        $query = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);

        if ($stmt->execute()) {
            $message = "User berhasil dihapus!";
        } else {
            $error = "Gagal menghapus user!";
        }
    }
}

// UPDATE ROLE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];

    $query = "UPDATE users SET role = :role WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':role', $new_role);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        $message = "Role user berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate role!";
    }
}

// EDIT USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    $query = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone, address = :address 
              WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        $message = "Data user berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate data user!";
    }
}

// RESET PASSWORD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = 'password123'; // Default password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $query = "UPDATE users SET password = :password WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        $message = "Password berhasil direset ke: <strong>password123</strong>";
    } else {
        $error = "Gagal mereset password!";
    }
}

// Filter
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';

// Ambil semua users dengan statistik
$query = "SELECT u.*, 
          COUNT(DISTINCT o.order_id) as total_orders,
          COALESCE(SUM(o.total_amount), 0) as total_spent
          FROM users u
          LEFT JOIN orders o ON u.user_id = o.user_id";

if ($filter_role) {
    $query .= " WHERE u.role = :role";
}

$query .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);

if ($filter_role) {
    $stmt->bindParam(':role', $filter_role);
}

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$query_stats = "SELECT 
                COUNT(CASE WHEN role = 'customer' THEN 1 END) as total_customers,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
                COUNT(*) as total_users
                FROM users";
$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Untuk edit
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $query_edit = "SELECT * FROM users WHERE user_id = :user_id";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->bindParam(':user_id', $edit_id);
    $stmt_edit->execute();
    $edit_user = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Alfaduro</title>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card.customers {
            border-left: 4px solid #3498db;
        }

        .stat-card.admins {
            border-left: 4px solid #e74c3c;
        }

        .stat-card.total {
            border-left: 4px solid #27ae60;
        }

        .stat-number {
            font-size: 42px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .role-customer {
            background: #d1ecf1;
            color: #0c5460;
        }

        .role-admin {
            background: #f8d7da;
            color: #721c24;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
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

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
        <h2><?php echo $edit_user ? '✏️ Edit User' : '👥 Kelola Pengguna'; ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($edit_user): ?>
            <!-- Form Edit User -->
            <div class="form-container">
                <h3>Edit Data User</h3>
                <form method="POST" action="">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">

                    <div class="form-group">
                        <label>Username (tidak bisa diubah)</label>
                        <input type="text" value="<?php echo $edit_user['username']; ?>" readonly style="background: #f0f0f0;">
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="full_name" value="<?php echo $edit_user['full_name']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo $edit_user['email']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="phone" value="<?php echo $edit_user['phone']; ?>">
                    </div>

                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" rows="3"><?php echo $edit_user['address']; ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="edit_user" class="btn btn-success">💾 Update Data</button>
                        <a href="users.php" class="btn btn-primary">❌ Batal</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card customers">
                    <div class="stat-label">👥 Total Customers</div>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                </div>
                <div class="stat-card admins">
                    <div class="stat-label">👨‍💼 Total Admins</div>
                    <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                </div>
                <div class="stat-card total">
                    <div class="stat-label">👤 Total Users</div>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>

            <!-- Filter -->
            <div class="filter-buttons">
                <a href="users.php" class="filter-btn <?php echo !$filter_role ? 'active' : ''; ?>">Semua</a>
                <a href="users.php?role=customer" class="filter-btn <?php echo $filter_role == 'customer' ? 'active' : ''; ?>">Customers</a>
                <a href="users.php?role=admin" class="filter-btn <?php echo $filter_role == 'admin' ? 'active' : ''; ?>">Admins</a>
            </div>

            <!-- Users Table -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Telepon</th>
                        <th>Role</th>
                        <th>Total Orders</th>
                        <th>Total Belanja</th>
                        <th>Terdaftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><strong><?php echo $user['username']; ?></strong></td>
                                <td><?php echo $user['full_name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo $user['phone'] ?: '-'; ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['total_orders']; ?></td>
                                <td>Rp <?php echo number_format($user['total_spent'], 0, ',', '.'); ?></td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button onclick="viewUser(<?php echo $user['user_id']; ?>)" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px; margin: 2px;">
                                        👁️ Detail
                                    </button>
                                    <a href="users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-success" style="font-size: 12px; padding: 5px 10px; margin: 2px;">
                                        ✏️ Edit
                                    </a>
                                    <button onclick="changeRole(<?php echo $user['user_id']; ?>, '<?php echo $user['role']; ?>')" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px; margin: 2px;">
                                        🔄 Role
                                    </button>
                                    <button onclick="resetPassword(<?php echo $user['user_id']; ?>)" class="btn btn-success" style="font-size: 12px; padding: 5px 10px; margin: 2px;">
                                        🔑 Reset PW
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="users.php?delete=<?php echo $user['user_id']; ?>"
                                            class="btn btn-danger"
                                            style="font-size: 12px; padding: 5px 10px; margin: 2px;"
                                            onclick="return confirm('Yakin ingin menghapus user ini?')">
                                            🗑️ Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">Tidak ada user</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Modal View User -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
            <div id="userDetails"></div>
        </div>
    </div>

    <!-- Modal Change Role -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('roleModal')">&times;</span>
            <h2>Ubah Role User</h2>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="role_user_id">
                <div class="form-group">
                    <label>Role Baru:</label>
                    <select name="role" id="role_select" required>
                        <option value="customer">Customer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="update_role" class="btn btn-success" style="width: 100%;">Update Role</button>
            </form>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('resetModal')">&times;</span>
            <h2>Reset Password</h2>
            <p>Password akan direset ke: <strong>password123</strong></p>
            <p style="color: #e74c3c;">⚠️ User harus mengganti password setelah login!</p>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="reset_user_id">
                <button type="submit" name="reset_password" class="btn btn-danger" style="width: 100%;">Reset Password</button>
                <button type="button" onclick="closeModal('resetModal')" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Batal</button>
            </form>
        </div>
    </div>

    <script>
        function viewUser(userId) {
            document.getElementById('viewModal').style.display = 'block';
            document.getElementById('userDetails').innerHTML = '<p>Loading user details...</p>';

            // Simplified - in production use AJAX
            setTimeout(() => {
                document.getElementById('userDetails').innerHTML =
                    '<h3>Detail User #' + userId + '</h3>' +
                    '<p>Detail lengkap user akan ditampilkan di sini...</p>';
            }, 500);
        }

        function changeRole(userId, currentRole) {
            document.getElementById('roleModal').style.display = 'block';
            document.getElementById('role_user_id').value = userId;
            document.getElementById('role_select').value = currentRole;
        }

        function resetPassword(userId) {
            document.getElementById('resetModal').style.display = 'block';
            document.getElementById('reset_user_id').value = userId;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>