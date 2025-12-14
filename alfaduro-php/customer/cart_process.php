<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// TAMBAH KE CART
if (isset($_POST['add_to_cart'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // Cek stok produk
    $query_stock = "SELECT stock_quantity FROM inventory WHERE product_id = :product_id";
    $stmt_stock = $conn->prepare($query_stock);
    $stmt_stock->bindParam(':product_id', $product_id);
    $stmt_stock->execute();
    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
    
    if ($stock && $stock['stock_quantity'] >= $quantity) {
        // Cek apakah user sudah punya cart
        $query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id";
        $stmt_cart = $conn->prepare($query_cart);
        $stmt_cart->bindParam(':user_id', $user_id);
        $stmt_cart->execute();
        
        if ($stmt_cart->rowCount() > 0) {
            $cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
            $cart_id = $cart['cart_id'];
        } else {
            // Buat cart baru
            $query_new_cart = "INSERT INTO carts (user_id) VALUES (:user_id)";
            $stmt_new_cart = $conn->prepare($query_new_cart);
            $stmt_new_cart->bindParam(':user_id', $user_id);
            $stmt_new_cart->execute();
            $cart_id = $conn->lastInsertId();
        }
        
        // Cek apakah produk sudah ada di cart
        $query_check = "SELECT * FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id";
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bindParam(':cart_id', $cart_id);
        $stmt_check->bindParam(':product_id', $product_id);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            // Update quantity
            $query_update = "UPDATE cart_items SET quantity = quantity + :quantity 
                           WHERE cart_id = :cart_id AND product_id = :product_id";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bindParam(':cart_id', $cart_id);
            $stmt_update->bindParam(':product_id', $product_id);
            $stmt_update->bindParam(':quantity', $quantity);
            $stmt_update->execute();
        } else {
            // Insert item baru
            $query_insert = "INSERT INTO cart_items (cart_id, product_id, quantity) 
                           VALUES (:cart_id, :product_id, :quantity)";
            $stmt_insert = $conn->prepare($query_insert);
            $stmt_insert->bindParam(':cart_id', $cart_id);
            $stmt_insert->bindParam(':product_id', $product_id);
            $stmt_insert->bindParam(':quantity', $quantity);
            $stmt_insert->execute();
        }
        
        $_SESSION['message'] = "Produk berhasil ditambahkan ke keranjang!";
    } else {
        $_SESSION['error'] = "Stok tidak mencukupi!";
    }
    
    header("Location: index.php");
    exit();
}

// UPDATE QUANTITY
if (isset($_POST['update_quantity'])) {
    $cart_item_id = $_POST['cart_item_id'];
    $quantity = $_POST['quantity'];
    $product_id = $_POST['product_id'];
    
    // Cek stok
    $query_stock = "SELECT stock_quantity FROM inventory WHERE product_id = :product_id";
    $stmt_stock = $conn->prepare($query_stock);
    $stmt_stock->bindParam(':product_id', $product_id);
    $stmt_stock->execute();
    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
    
    if ($stock && $stock['stock_quantity'] >= $quantity) {
        $query = "UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':cart_item_id', $cart_item_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Quantity berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Stok tidak mencukupi!";
    }
    
    header("Location: cart.php");
    exit();
}

// HAPUS ITEM
if (isset($_GET['remove'])) {
    $cart_item_id = $_GET['remove'];
    
    $query = "DELETE FROM cart_items WHERE cart_item_id = :cart_item_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':cart_item_id', $cart_item_id);
    $stmt->execute();
    
    $_SESSION['message'] = "Item berhasil dihapus dari keranjang!";
    header("Location: cart.php");
    exit();
}

// CLEAR CART
if (isset($_GET['clear'])) {
    $user_id = $_SESSION['user_id'];
    
    $query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id";
    $stmt_cart = $conn->prepare($query_cart);
    $stmt_cart->bindParam(':user_id', $user_id);
    $stmt_cart->execute();
    
    if ($stmt_cart->rowCount() > 0) {
        $cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
        $cart_id = $cart['cart_id'];
        
        $query = "DELETE FROM cart_items WHERE cart_id = :cart_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':cart_id', $cart_id);
        $stmt->execute();
        
        $_SESSION['message'] = "Keranjang berhasil dikosongkan!";
    }
    
    header("Location: cart.php");
    exit();
}

header("Location: cart.php");
exit();
?>