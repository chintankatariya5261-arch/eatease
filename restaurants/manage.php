<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email'])) {
  header('Location: ../auth/login.php');
  exit;
}

$sessionRole = $_SESSION['role'] ?? 'user';
if (!in_array($sessionRole, ['restaurant_owner', 'admin'], true)) {
  header('Location: ../index.php');
  exit;
}

$page_title = 'Manage Restaurants & Menu | EatEase';

$restaurant_success = '';
$menu_success = '';
$review_success = '';
$error_messages = [];
$edit_restaurant = null;
$owner_id_global = null;
if ($sessionRole === 'restaurant_owner') {
  $u_email = $_SESSION['email'];
  $u_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
  $u_stmt->bind_param("s", $u_email);
  $u_stmt->execute();
  $u_res = $u_stmt->get_result();
  if ($u_row = $u_res->fetch_assoc()) {
    $owner_id_global = $u_row['id'];
  }
  $u_stmt->close();
}

// Handle Edit Mode
if (isset($_GET['edit_restaurant'])) {
  $edit_id = intval($_GET['edit_restaurant']);
  if ($edit_id > 0) {
    // If admin, can edit any. If owner, only own.
    if ($sessionRole === 'admin') {
      $stmt = $mysqli->prepare("SELECT * FROM hotels WHERE id = ?");
      $stmt->bind_param("i", $edit_id);
    } else {
      $u_email = $_SESSION['email'];
      $u_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
      $u_stmt->bind_param("s", $u_email);
      $u_stmt->execute();
      $u_res = $u_stmt->get_result();
      $u_data = $u_res->fetch_assoc();
      $owner_id = $u_data['id'];
      $u_stmt->close();

      $stmt = $mysqli->prepare("SELECT * FROM hotels WHERE id = ? AND owner_id = ?");
      $stmt->bind_param("ii", $edit_id, $owner_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $edit_restaurant = $row;
    }
    $stmt->close();
  }
}


// Check if deleted_at column exists, if not add it
try {
  $check_column = $mysqli->query("SHOW COLUMNS FROM hotels LIKE 'deleted_at'");
  if ($check_column->num_rows == 0) {
    $mysqli->query("ALTER TABLE hotels ADD COLUMN deleted_at datetime DEFAULT NULL AFTER updated_at");
    $mysqli->query("ALTER TABLE hotels ADD INDEX idx_deleted_at (deleted_at)");
  }
} catch (Exception $e) {
  // Column might already exist or there's an issue, continue anyway
}
try {
  $open_col = $mysqli->query("SHOW COLUMNS FROM hotels LIKE 'open_time'");
  if ($open_col->num_rows == 0) {
    $mysqli->query("ALTER TABLE hotels ADD COLUMN open_time TIME DEFAULT NULL AFTER phone");
  }
  $close_col = $mysqli->query("SHOW COLUMNS FROM hotels LIKE 'close_time'");
  if ($close_col->num_rows == 0) {
    $mysqli->query("ALTER TABLE hotels ADD COLUMN close_time TIME DEFAULT NULL AFTER open_time");
  }
  $img_col = $mysqli->query("SHOW COLUMNS FROM hotels LIKE 'image_url'");
  if ($img_col->num_rows == 0) {
    $mysqli->query("ALTER TABLE hotels ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER close_time");
  }
} catch (Exception $e) {
}
try {
  $owner_col = $mysqli->query("SHOW COLUMNS FROM hotels LIKE 'owner_id'");
  if ($owner_col->num_rows == 0) {
    $mysqli->query("ALTER TABLE hotels ADD COLUMN owner_id int(10) UNSIGNED DEFAULT NULL AFTER total_ratings");
    $mysqli->query("ALTER TABLE hotels ADD INDEX idx_owner_id (owner_id)");
  }
} catch (Exception $e) {
}
try {
  $hidden_col_r = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
  if ($hidden_col_r && $hidden_col_r->num_rows == 0) {
    $mysqli->query("ALTER TABLE ratings ADD COLUMN hidden_at datetime DEFAULT NULL AFTER created_at");
    $mysqli->query("ALTER TABLE ratings ADD INDEX idx_hidden_at (hidden_at)");
  }
  $updated_col_r = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'updated_at'");
  if ($updated_col_r && $updated_col_r->num_rows == 0) {
    $mysqli->query("ALTER TABLE ratings ADD COLUMN updated_at datetime DEFAULT NULL AFTER hidden_at");
  }
} catch (Exception $e) {
}

function upload_image(array $file, string $folder): ?string
{
  if (empty($file['name'])) {
    return null;
  }

  $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($file['type'], $allowed_types, true)) {
    throw new RuntimeException('Only JPG, PNG, GIF or WEBP images are allowed.');
  }

  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Failed to upload file. Please try again.');
  }

  if ($file['size'] > 3 * 1024 * 1024) {
    throw new RuntimeException('Image must be under 3MB.');
  }

  $upload_root = __DIR__ . '/../uploads/' . $folder . '/';
  if (!is_dir($upload_root)) {
    mkdir($upload_root, 0775, true);
  }

  $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
  $filename = $folder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
  $destination = $upload_root . $filename;

  if (!move_uploaded_file($file['tmp_name'], $destination)) {
    throw new RuntimeException('Could not save uploaded file.');
  }

  return 'uploads/' . $folder . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_review']) && ($sessionRole === 'restaurant_owner' || $sessionRole === 'admin')) {
    $review_id = intval($_POST['review_id'] ?? 0);
    $new_rating = intval($_POST['new_rating'] ?? 0);
    $new_review = trim($_POST['new_review'] ?? '');
    if ($review_id <= 0 || $new_rating < 1 || $new_rating > 5) {
      $error_messages[] = 'Invalid review update data.';
    } else {
      try {
        $own_ok = true;
        $hotel_id_for_review = null;
        if ($sessionRole !== 'admin') {
          $own_check = $mysqli->prepare("SELECT h.owner_id, r.hotel_id FROM ratings r JOIN hotels h ON r.hotel_id = h.id WHERE r.id = ?");
          $own_check->bind_param("i", $review_id);
          $own_check->execute();
          $own_res = $own_check->get_result();
          $own_row = $own_res->fetch_assoc();
          $own_check->close();
          if (!$own_row || intval($own_row['owner_id']) !== intval($owner_id_global)) {
            $own_ok = false;
          } else {
            $hotel_id_for_review = intval($own_row['hotel_id']);
          }
        } else {
          $hid_stmt = $mysqli->prepare("SELECT hotel_id FROM ratings WHERE id = ?");
          $hid_stmt->bind_param("i", $review_id);
          $hid_stmt->execute();
          $hid_res = $hid_stmt->get_result();
          $hid_row = $hid_res->fetch_assoc();
          $hid_stmt->close();
          if ($hid_row) {
            $hotel_id_for_review = intval($hid_row['hotel_id']);
          }
        }
        if (!$own_ok) {
          $error_messages[] = 'You are not allowed to update this review.';
          throw new RuntimeException('Unauthorized');
        }
        $stmt = $mysqli->prepare("UPDATE ratings SET rating = ?, review = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("isi", $new_rating, $new_review, $review_id);
        if ($stmt->execute()) {
          $review_success = 'Review updated successfully.';
        } else {
          $error_messages[] = 'Failed to update review.';
        }
        $stmt->close();
        if ($hotel_id_for_review) {
          $has_hidden_col = false;
          try {
            $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
            if ($col_res && $col_res->num_rows > 0) {
              $has_hidden_col = true;
            }
          } catch (Throwable $th) {
          }
          $avg_sql = $has_hidden_col
            ? "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL"
            : "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ?";
          $avg_stmt = $mysqli->prepare($avg_sql);
          $avg_stmt->bind_param("i", $hotel_id_for_review);
          $avg_stmt->execute();
          $avg_result = $avg_stmt->get_result();
          $avg_data = $avg_result->fetch_assoc();
          $avg_stmt->close();
          $avg_rating = $avg_data && $avg_data['avg_rating'] !== null ? round($avg_data['avg_rating'], 2) : 0;
          $total_ratings = intval($avg_data['total_ratings'] ?? 0);
          $update_hotel_stmt = $mysqli->prepare("UPDATE hotels SET avg_rating = ?, total_ratings = ? WHERE id = ?");
          $update_hotel_stmt->bind_param("dii", $avg_rating, $total_ratings, $hotel_id_for_review);
          $update_hotel_stmt->execute();
          $update_hotel_stmt->close();
        }
      } catch (Throwable $th) {
      }
    }
  }
  if (isset($_POST['toggle_hide_review']) && ($sessionRole === 'restaurant_owner' || $sessionRole === 'admin')) {
    $review_id = intval($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? 'hide';
    if ($review_id <= 0) {
      $error_messages[] = 'Invalid review ID.';
    } else {
      try {
        $own_ok = true;
        $hotel_id_for_review = null;
        if ($sessionRole !== 'admin') {
          $own_check = $mysqli->prepare("SELECT h.owner_id, r.hotel_id FROM ratings r JOIN hotels h ON r.hotel_id = h.id WHERE r.id = ?");
          $own_check->bind_param("i", $review_id);
          $own_check->execute();
          $own_res = $own_check->get_result();
          $own_row = $own_res->fetch_assoc();
          $own_check->close();
          if (!$own_row || intval($own_row['owner_id']) !== intval($owner_id_global)) {
            $own_ok = false;
          } else {
            $hotel_id_for_review = intval($own_row['hotel_id']);
          }
        } else {
          $hid_stmt = $mysqli->prepare("SELECT hotel_id FROM ratings WHERE id = ?");
          $hid_stmt->bind_param("i", $review_id);
          $hid_stmt->execute();
          $hid_res = $hid_stmt->get_result();
          $hid_row = $hid_res->fetch_assoc();
          $hid_stmt->close();
          if ($hid_row) {
            $hotel_id_for_review = intval($hid_row['hotel_id']);
          }
        }
        if (!$own_ok) {
          $error_messages[] = 'You are not allowed to update this review.';
          throw new RuntimeException('Unauthorized');
        }
        $has_hidden_col = false;
        try {
          $col_res = $mysqli->query("SHOW COLUMNS FROM ratings LIKE 'hidden_at'");
          if ($col_res && $col_res->num_rows > 0) {
            $has_hidden_col = true;
          }
        } catch (Throwable $th) {
        }
        if ($has_hidden_col) {
          if ($action === 'unhide') {
            $stmt = $mysqli->prepare("UPDATE ratings SET hidden_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $review_id);
          } else {
            $stmt = $mysqli->prepare("UPDATE ratings SET hidden_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $review_id);
          }
          if ($stmt->execute()) {
            $review_success = $action === 'unhide' ? 'Review unhidden.' : 'Review hidden.';
          } else {
            $error_messages[] = 'Failed to update review visibility.';
          }
          $stmt->close();
        } else {
          $error_messages[] = 'Hide feature is unavailable.';
        }
        if ($hotel_id_for_review) {
          $avg_sql = $has_hidden_col
            ? "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ? AND hidden_at IS NULL"
            : "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM ratings WHERE hotel_id = ?";
          $avg_stmt = $mysqli->prepare($avg_sql);
          $avg_stmt->bind_param("i", $hotel_id_for_review);
          $avg_stmt->execute();
          $avg_result = $avg_stmt->get_result();
          $avg_data = $avg_result->fetch_assoc();
          $avg_stmt->close();
          $avg_rating = $avg_data && $avg_data['avg_rating'] !== null ? round($avg_data['avg_rating'], 2) : 0;
          $total_ratings = intval($avg_data['total_ratings'] ?? 0);
          $update_hotel_stmt = $mysqli->prepare("UPDATE hotels SET avg_rating = ?, total_ratings = ? WHERE id = ?");
          $update_hotel_stmt->bind_param("dii", $avg_rating, $total_ratings, $hotel_id_for_review);
          $update_hotel_stmt->execute();
          $update_hotel_stmt->close();
        }
      } catch (Throwable $th) {
      }
    }
  }
  // Handle delete restaurant (admin only)
  if (isset($_POST['delete_restaurant']) && $sessionRole === 'admin') {
    $restaurant_id = intval($_POST['restaurant_id'] ?? 0);
    if ($restaurant_id > 0) {
      // Soft delete - add deleted_at timestamp
      $stmt = $mysqli->prepare("UPDATE hotels SET deleted_at = NOW() WHERE id = ?");
      $stmt->bind_param("i", $restaurant_id);
      if ($stmt->execute()) {
        $restaurant_success = 'Restaurant deleted successfully.';
      } else {
        $error_messages[] = 'Failed to delete restaurant.';
      }
      $stmt->close();
    }
  }

  if (isset($_POST['update_restaurant']) && ($sessionRole === 'restaurant_owner' || $sessionRole === 'admin')) {
    $restaurant_id = intval($_POST['restaurant_id'] ?? 0);
    $name = trim($_POST['restaurant_name'] ?? '');
    $cuisine = trim($_POST['cuisine_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $price_range = trim($_POST['price_range'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $open_time = trim($_POST['open_time'] ?? '');
    $close_time = trim($_POST['close_time'] ?? '');
    if ($restaurant_id <= 0) {
      $error_messages[] = 'Invalid restaurant ID.';
    } elseif ($name === '' || $cuisine === '' || $location === '') {
      $error_messages[] = 'Please fill in all required restaurant fields.';
    } elseif (!empty($phone) && (!preg_match('/^[0-9]{10}$/', $phone))) {
      $error_messages[] = 'Phone number must be exactly 10 digits.';
    } else {
      try {
        if ($sessionRole !== 'admin') {
          $own_check = $mysqli->prepare("SELECT id FROM hotels WHERE id = ? AND owner_id = ?");
          $own_check->bind_param("ii", $restaurant_id, $owner_id_global);
          $own_check->execute();
          $own_res = $own_check->get_result();
          $own_row = $own_res->fetch_assoc();
          $own_check->close();
          if (!$own_row) {
            $error_messages[] = 'You are not allowed to update this restaurant.';
            throw new RuntimeException('Unauthorized');
          }
        }
        $open_time_param = ($open_time !== '') ? $open_time : null;
        $close_time_param = ($close_time !== '') ? $close_time : null;

        if (!empty($_FILES['restaurant_image']['name'])) {
          $image_path = upload_image($_FILES['restaurant_image'], 'restaurants');
          $sql = "UPDATE hotels SET name = ?, description = ?, cuisine_type = ?, location = ?, phone = ?, price_range = ?, open_time = ?, close_time = ?, image_url = ?, updated_at = NOW() WHERE id = ?";
          $stmt = $mysqli->prepare($sql);
          if (!$stmt) {
            throw new RuntimeException('Failed to prepare update query: ' . $mysqli->error);
          }
          $stmt->bind_param("sssssssssi", $name, $description, $cuisine, $location, $phone, $price_range, $open_time_param, $close_time_param, $image_path, $restaurant_id);
        } else {
          $sql = "UPDATE hotels SET name = ?, description = ?, cuisine_type = ?, location = ?, phone = ?, price_range = ?, open_time = ?, close_time = ?, updated_at = NOW() WHERE id = ?";
          $stmt = $mysqli->prepare($sql);
          if (!$stmt) {
            throw new RuntimeException('Failed to prepare update query: ' . $mysqli->error);
          }
          $stmt->bind_param("ssssssssi", $name, $description, $cuisine, $location, $phone, $price_range, $open_time_param, $close_time_param, $restaurant_id);
        }

        if ($stmt->execute()) {
          $restaurant_success = 'Restaurant updated successfully.';
        } else {
          $error_messages[] = 'Failed to update restaurant: ' . $stmt->error;
        }
        $stmt->close();
      } catch (Throwable $th) {
        $error_messages[] = $th->getMessage();
      }
    }
  }

  if (isset($_POST['add_restaurant']) && $sessionRole === 'restaurant_owner') {
    $name = trim($_POST['restaurant_name'] ?? '');
    $cuisine = trim($_POST['cuisine_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $price_range = trim($_POST['price_range'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $open_time = trim($_POST['open_time'] ?? '');
    $close_time = trim($_POST['close_time'] ?? '');

    if ($name === '' || $cuisine === '' || $location === '') {
      $error_messages[] = 'Please fill in all required restaurant fields.';
    } elseif (!empty($phone) && (!preg_match('/^[0-9]{10}$/', $phone))) {
      $error_messages[] = 'Phone number must be exactly 10 digits.';
    } else {
      try {
        $image_path = null;
        if (!empty($_FILES['restaurant_image']['name'])) {
          $image_path = upload_image($_FILES['restaurant_image'], 'restaurants');
        }

        $owner_id = $owner_id_global;
        if (!$owner_id) {
          $owner_email = $_SESSION['email'];
          $owner_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
          if (!$owner_stmt) {
            throw new RuntimeException('Failed to prepare owner lookup query: ' . $mysqli->error);
          }
          $owner_stmt->bind_param("s", $owner_email);
          $owner_stmt->execute();
          $owner_result = $owner_stmt->get_result();
          $owner = $owner_result->fetch_assoc();
          $owner_id = $owner ? (int)$owner['id'] : 0;
          $owner_stmt->close();
        }
        if ($owner_id <= 0) {
          throw new RuntimeException('Could not determine your user account. Please log out and log back in.');
        }

        $mysqli->begin_transaction();
        $open_time_param = ($open_time !== '') ? $open_time : null;
        $close_time_param = ($close_time !== '') ? $close_time : null;

        $stmt = $mysqli->prepare("INSERT INTO hotels (name, description, cuisine_type, location, phone, price_range, open_time, close_time, image_url, owner_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
          throw new RuntimeException('Failed to prepare insert query: ' . $mysqli->error);
        }
        $stmt->bind_param("sssssssssi", $name, $description, $cuisine, $location, $phone, $price_range, $open_time_param, $close_time_param, $image_path, $owner_id);
        if ($stmt->execute()) {
          $stmt->close();
          $mysqli->commit();
          $restaurant_success = 'Restaurant added successfully.';
          $backup_dir = __DIR__ . '/../backups';
          if (!is_dir($backup_dir)) {
            @mkdir($backup_dir, 0775, true);
          }
          $csv = $backup_dir . '/restaurants.csv';
          $line = implode(',', [
            date('Y-m-d H:i:s'),
            str_replace(',', ' ', $name),
            str_replace(',', ' ', $cuisine),
            str_replace(',', ' ', $location),
            $phone,
            $price_range,
            (string)$owner_id
          ]) . "\n";
          @file_put_contents($csv, $line, FILE_APPEND);
        } else {
          $err = $stmt->error;
          $stmt->close();
          $mysqli->rollback();
          $error_messages[] = 'Failed to save restaurant: ' . $err;
        }
      } catch (Throwable $th) {
        try {
          $mysqli->rollback();
        } catch (Throwable $rollbackErr) {
        }
        $error_messages[] = $th->getMessage();
      }
    }
  }

  if (isset($_POST['add_menu_item']) && $sessionRole === 'restaurant_owner') {
    $hotel_id = intval($_POST['hotel_id'] ?? 0);
    $item_name = trim($_POST['menu_name'] ?? '');
    $menu_description = trim($_POST['menu_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);

    if ($hotel_id <= 0 || $item_name === '' || $price <= 0) {
      $error_messages[] = 'Please select a restaurant, enter a menu item name, and provide a valid price.';
    } else {
      try {
        $menu_image_path = null;
        if (!empty($_FILES['menu_image']['name'])) {
          $menu_image_path = upload_image($_FILES['menu_image'], 'menu');
        }

        $mysqli->begin_transaction();
        $stmt = $mysqli->prepare("INSERT INTO menu_items (hotel_id, name, description, price, image_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issds", $hotel_id, $item_name, $menu_description, $price, $menu_image_path);
        if ($stmt->execute()) {
          $stmt->close();
          $mysqli->commit();
          $menu_success = 'Menu item added successfully.';
          $backup_dir = __DIR__ . '/../backups';
          if (!is_dir($backup_dir)) {
            @mkdir($backup_dir, 0775, true);
          }
          $csv = $backup_dir . '/menu_items.csv';
          $line = implode(',', [
            date('Y-m-d H:i:s'),
            (string)$hotel_id,
            str_replace(',', ' ', $item_name),
            number_format($price, 2),
            str_replace(',', ' ', (string)$menu_image_path)
          ]) . "\n";
          @file_put_contents($csv, $line, FILE_APPEND);
        } else {
          $err = $stmt->error;
          $stmt->close();
          $mysqli->rollback();
          $error_messages[] = 'Failed to save menu item: ' . $err;
        }
      } catch (Throwable $th) {
        $error_messages[] = $th->getMessage();
      }
    }
  }
  if (isset($_POST['update_menu_item']) && $sessionRole === 'restaurant_owner') {
    $menu_item_id = intval($_POST['menu_item_id'] ?? 0);
    $item_name = trim($_POST['menu_name'] ?? '');
    $menu_description = trim($_POST['menu_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    if ($menu_item_id <= 0 || $item_name === '' || $price <= 0) {
      $error_messages[] = 'Enter a valid name and price.';
    } else {
      try {
        $own_check = $mysqli->prepare("SELECT h.owner_id FROM menu_items m JOIN hotels h ON m.hotel_id = h.id WHERE m.id = ?");
        $own_check->bind_param("i", $menu_item_id);
        $own_check->execute();
        $own_res = $own_check->get_result();
        $own_row = $own_res->fetch_assoc();
        $own_check->close();
        if (!$own_row || $own_row['owner_id'] != $owner_id_global) {
          $error_messages[] = 'You are not allowed to update this menu item.';
          throw new RuntimeException('Unauthorized');
        }
        if (!empty($_FILES['menu_image']['name'])) {
          $menu_image_path = upload_image($_FILES['menu_image'], 'menu');
          $sql = "UPDATE menu_items SET name = ?, description = ?, price = ?, image_path = ? WHERE id = ?";
          $stmt = $mysqli->prepare($sql);
          if (!$stmt) {
            throw new RuntimeException('Failed to prepare update query: ' . $mysqli->error);
          }
          $stmt->bind_param("ssdsi", $item_name, $menu_description, $price, $menu_image_path, $menu_item_id);
        } else {
          $sql = "UPDATE menu_items SET name = ?, description = ?, price = ? WHERE id = ?";
          $stmt = $mysqli->prepare($sql);
          if (!$stmt) {
            throw new RuntimeException('Failed to prepare update query: ' . $mysqli->error);
          }
          $stmt->bind_param("ssdi", $item_name, $menu_description, $price, $menu_item_id);
        }
        if ($stmt->execute()) {
          $menu_success = 'Menu item updated successfully.';
        } else {
          $error_messages[] = 'Failed to update menu item: ' . $stmt->error;
        }
        $stmt->close();
      } catch (Throwable $th) {
        $error_messages[] = $th->getMessage();
      }
    }
  }
  if (isset($_POST['delete_menu_item']) && $sessionRole === 'restaurant_owner') {
    $menu_item_id = intval($_POST['menu_item_id'] ?? 0);
    if ($menu_item_id > 0) {
      try {
        $own_check = $mysqli->prepare("SELECT h.owner_id FROM menu_items m JOIN hotels h ON m.hotel_id = h.id WHERE m.id = ?");
        $own_check->bind_param("i", $menu_item_id);
        $own_check->execute();
        $own_res = $own_check->get_result();
        $own_row = $own_res->fetch_assoc();
        $own_check->close();
        if (!$own_row || $own_row['owner_id'] != $owner_id_global) {
          $error_messages[] = 'You are not allowed to delete this menu item.';
          throw new RuntimeException('Unauthorized');
        }
        $stmt = $mysqli->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $menu_item_id);
        if ($stmt->execute()) {
          $menu_success = 'Menu item deleted successfully.';
        } else {
          $error_messages[] = 'Failed to delete menu item.';
        }
        $stmt->close();
      } catch (Throwable $th) {
      }
    }
  }
}

// Get restaurants based on role
$restaurants = [];
if ($sessionRole === 'admin') {
  // Admin: Show all restaurants with owner info (not deleted)
  $restaurants_query = "
    SELECT h.id, h.name, h.cuisine_type, h.location, h.phone, h.created_at,
           u.first_name, u.last_name, u.email as owner_email
    FROM hotels h
    LEFT JOIN users u ON h.owner_id = u.id
    WHERE h.deleted_at IS NULL
    ORDER BY h.created_at DESC
  ";
  $restaurants_result = $mysqli->query($restaurants_query);
  if ($restaurants_result) {
    while ($row = $restaurants_result->fetch_assoc()) {
      $restaurants[] = $row;
    }
  }
} else {
  // Restaurant owner: Show only their restaurants
  $owner_email = $_SESSION['email'];
  $owner_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
  $owner_stmt->bind_param("s", $owner_email);
  $owner_stmt->execute();
  $owner_result = $owner_stmt->get_result();
  $owner = $owner_result->fetch_assoc();
  $owner_id = $owner ? $owner['id'] : 0;
  $owner_stmt->close();

  $restaurants_query = "
    SELECT id, name, cuisine_type 
    FROM hotels 
    WHERE owner_id = ? AND deleted_at IS NULL
    ORDER BY name ASC
  ";
  $restaurants_stmt = $mysqli->prepare($restaurants_query);
  $restaurants_stmt->bind_param("i", $owner_id);
  $restaurants_stmt->execute();
  $restaurants_result = $restaurants_stmt->get_result();
  while ($row = $restaurants_result->fetch_assoc()) {
    $restaurants[] = $row;
  }
  $restaurants_stmt->close();
}

// For menu dropdown (only owner's non-deleted restaurants if owner)
$restaurants_for_menu = [];
if ($sessionRole === 'restaurant_owner' && $owner_id_global) {
  if ($edit_restaurant) {
    $restaurants_for_menu[] = [
      'id' => $edit_restaurant['id'],
      'name' => $edit_restaurant['name'],
      'cuisine_type' => $edit_restaurant['cuisine_type'] ?? ''
    ];
  } else {
    $menu_restaurants_stmt = $mysqli->prepare("SELECT id, name, cuisine_type FROM hotels WHERE owner_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $menu_restaurants_stmt->bind_param("i", $owner_id_global);
    $menu_restaurants_stmt->execute();
    $menu_restaurants_result = $menu_restaurants_stmt->get_result();
    if ($menu_restaurants_result) {
      while ($row = $menu_restaurants_result->fetch_assoc()) {
        $restaurants_for_menu[] = $row;
      }
    }
  }
} else {
  $menu_restaurants_result = $mysqli->query("SELECT id, name, cuisine_type FROM hotels WHERE deleted_at IS NULL ORDER BY name ASC");
  if ($menu_restaurants_result) {
    while ($row = $menu_restaurants_result->fetch_assoc()) {
      $restaurants_for_menu[] = $row;
    }
  }
  if (isset($menu_restaurants_stmt) && $menu_restaurants_stmt instanceof mysqli_stmt) {
    $menu_restaurants_stmt->close();
  }
}

$recent_menus = [];
if ($sessionRole === 'restaurant_owner' && $owner_id_global) {
  $stmt = $mysqli->prepare("SELECT m.id, m.name, m.price, m.image_path, h.name AS hotel_name FROM menu_items m JOIN hotels h ON m.hotel_id = h.id WHERE h.deleted_at IS NULL AND h.owner_id = ? ORDER BY m.created_at DESC LIMIT 5");
  $stmt->bind_param("i", $owner_id_global);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $recent_menus[] = $row;
  }
  $stmt->close();
} else {
  $menu_result = $mysqli->query("SELECT m.id, m.name, m.price, m.image_path, h.name AS hotel_name FROM menu_items m JOIN hotels h ON m.hotel_id = h.id WHERE h.deleted_at IS NULL ORDER BY m.created_at DESC LIMIT 5");
  if ($menu_result) {
    while ($row = $menu_result->fetch_assoc()) {
      $recent_menus[] = $row;
    }
  }
}

$owner_menu_items = [];
if ($sessionRole === 'restaurant_owner' && $owner_id_global && $edit_restaurant) {
  $mi_stmt = $mysqli->prepare("SELECT m.id, m.name, m.description, m.price, m.image_path, m.hotel_id, h.name AS hotel_name FROM menu_items m JOIN hotels h ON m.hotel_id = h.id WHERE m.hotel_id = ? AND h.owner_id = ? ORDER BY m.created_at DESC");
  $hotel_id_edit = intval($edit_restaurant['id']);
  $mi_stmt->bind_param("ii", $hotel_id_edit, $owner_id_global);
  $mi_stmt->execute();
  $mi_res = $mi_stmt->get_result();
  while ($row = $mi_res->fetch_assoc()) {
    $owner_menu_items[] = $row;
  }
  $mi_stmt->close();
}
$moderation_reviews = [];
if ($edit_restaurant) {
  $rid = intval($edit_restaurant['id']);
  $rev_stmt = $mysqli->prepare("SELECT r.id, r.rating, r.review, r.created_at, r.hidden_at, u.email AS user_email FROM ratings r JOIN users u ON r.user_id = u.id WHERE r.hotel_id = ? ORDER BY r.created_at DESC LIMIT 50");
  $rev_stmt->bind_param("i", $rid);
  $rev_stmt->execute();
  $rev_res = $rev_stmt->get_result();
  while ($row = $rev_res->fetch_assoc()) {
    $moderation_reviews[] = $row;
  }
  $rev_stmt->close();
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/manage-restaurants.css">
<?php if ($sessionRole === 'admin'): ?>
<link rel="stylesheet" href="../assets/css/admin-dashboard.css">
<?php endif; ?>

<section class="manage-wrapper">
  <div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <?php if ($sessionRole === 'admin'): ?>
          <p class="eyebrow">Admin Panel</p>
          <h1>Manage Restaurants</h1>
          <p class="muted">View and manage all restaurant owner data</p>
        <?php else: ?>
          <p class="eyebrow">Create listings</p>
          <h1>Manage Menu</h1>
          <p class="muted">Add or edit menu items for your restaurants</p>
        <?php endif; ?>
      </div>
      <?php if ($sessionRole === 'admin'): ?>
        <div>
          <a href="../admin/dashboard.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
          </a>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($sessionRole === 'admin'): ?>
      <?php
      $restaurant_stats = ['total' => 0, 'active' => 0, 'deleted' => 0];
      if ($res = $mysqli->query("SELECT COUNT(*) AS total, SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS deleted FROM hotels")) {
        $restaurant_stats = $res->fetch_assoc() ?: $restaurant_stats;
      }
      ?>
      <div class="stats-grid" style="margin-top: 10px;">
        <a href="../restaurants/manage.php" style="text-decoration: none; color: inherit; display: block;">
          <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
              <i class="fas fa-store"></i>
            </div>
            <div class="stat-content">
              <h3><?php echo intval($restaurant_stats['total']); ?></h3>
              <p>Total Restaurants</p>
            </div>
          </div>
        </a>
        <a href="../restaurants/manage.php" style="text-decoration: none; color: inherit; display: block;">
          <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
              <h3><?php echo intval($restaurant_stats['active']); ?></h3>
              <p>Active Restaurants</p>
            </div>
          </div>
        </a>
        <a href="../admin/deleted-restaurants.php" style="text-decoration: none; color: inherit; display: block;">
          <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
              <i class="fas fa-trash-alt"></i>
            </div>
            <div class="stat-content">
              <h3><?php echo intval($restaurant_stats['deleted']); ?></h3>
              <p>Deleted Restaurants</p>
            </div>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($error_messages as $msg): ?>
            <li><?php echo htmlspecialchars($msg); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($restaurant_success): ?>
      <div class="alert alert-success">
        <span><?php echo htmlspecialchars($restaurant_success); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($menu_success): ?>
      <div class="alert alert-success">
        <span><?php echo htmlspecialchars($menu_success); ?></span>
      </div>
    <?php endif; ?>
    <?php if (!empty($review_success)): ?>
      <div class="alert alert-success">
        <span><?php echo htmlspecialchars($review_success); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($sessionRole === 'restaurant_owner' && $edit_restaurant): ?>
      <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
          <h3>Your Menu Items</h3>
          <p class="muted">Update or remove your dishes</p>
        </div>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                <th style="padding: 12px; text-align: left;">Dish</th>
                <th style="padding: 12px; text-align: left;">Restaurant</th>
                <th style="padding: 12px; text-align: left;">Price</th>
                <th style="padding: 12px; text-align: left;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($owner_menu_items)): ?>
                <?php foreach ($owner_menu_items as $item): ?>
                  <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?php echo htmlspecialchars($item['name']); ?></td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($item['hotel_name']); ?></td>
                    <td style="padding: 12px;">₹<?php echo number_format($item['price'], 2); ?></td>
                    <td style="padding: 12px;">
                      <form method="POST" enctype="multipart/form-data" style="display: inline-block; margin-right: 8px;">
                        <input type="hidden" name="update_menu_item" value="1">
                        <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                        <input type="text" name="menu_name" value="<?php echo htmlspecialchars($item['name']); ?>" style="padding: 6px; width: 160px;" required>
                        <input type="number" name="price" value="<?php echo htmlspecialchars($item['price']); ?>" min="1" step="0.01" style="padding: 6px; width: 120px;" required>
                        <input type="file" name="menu_image" accept="image/*" style="padding: 6px; width: 180px;">
                        <input type="text" name="menu_description" value="<?php echo htmlspecialchars($item['description']); ?>" style="padding: 6px; width: 220px;">
                        <button type="submit" style="background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                          <i class="fas fa-save"></i> Update
                        </button>
                      </form>
                      <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete this item?');">
                        <input type="hidden" name="delete_menu_item" value="1">
                        <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No menu items yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($edit_restaurant): ?>
      <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
          <h3>Reviews</h3>
          <p class="muted">Edit and toggle visibility</p>
        </div>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                <th style="padding: 12px; text-align: left;">User</th>
                <th style="padding: 12px; text-align: left;">Rating</th>
                <th style="padding: 12px; text-align: left;">Review</th>
                <th style="padding: 12px; text-align: left;">Created</th>
                <th style="padding: 12px; text-align: left;">Status</th>
                <th style="padding: 12px; text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($moderation_reviews)): ?>
                <?php foreach ($moderation_reviews as $rv): ?>
                  <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?php echo htmlspecialchars($rv['user_email']); ?></td>
                    <td style="padding: 12px; width: 120px;">
                      <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="update_review" value="1">
                        <input type="hidden" name="review_id" value="<?php echo intval($rv['id']); ?>">
                        <select name="new_rating" style="min-width: 80px;">
                          <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (intval($rv['rating']) === $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                          <?php endfor; ?>
                        </select>
                    </td>
                    <td style="padding: 12px;">
                      <textarea name="new_review" rows="2" style="width: 100%;"><?php echo htmlspecialchars($rv['review']); ?></textarea>
                    </td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars(date('Y-m-d', strtotime($rv['created_at']))); ?></td>
                    <td style="padding: 12px;"><?php echo $rv['hidden_at'] ? 'Hidden' : 'Visible'; ?></td>
                    <td style="padding: 12px; text-align: center; white-space: nowrap;">
                      <button type="submit" style="background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 6px;">
                        <i class="fas fa-save"></i> Update
                      </button>
                      </form>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="toggle_hide_review" value="1">
                        <input type="hidden" name="review_id" value="<?php echo intval($rv['id']); ?>">
                        <?php if ($rv['hidden_at']): ?>
                          <input type="hidden" name="action" value="unhide">
                          <button type="submit" style="background: #43e97b; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-eye"></i> Unhide
                          </button>
                        <?php else: ?>
                          <input type="hidden" name="action" value="hide">
                          <button type="submit" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-eye-slash"></i> Hide
                          </button>
                        <?php endif; ?>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="padding: 20px; text-align: center; color: #999;">No reviews found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>




    <div class="form-grid">
      <?php if ($sessionRole === 'restaurant_owner' || ($sessionRole === 'admin' && $edit_restaurant)): ?>
        <form method="POST" enctype="multipart/form-data" class="card">
          <?php if ($edit_restaurant): ?>
            <input type="hidden" name="update_restaurant" value="1">
            <input type="hidden" name="restaurant_id" value="<?php echo $edit_restaurant['id']; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h2>Edit Restaurant</h2>
              <a href="manage.php" class="btn-secondary" style="font-size: 0.9rem;">Cancel Edit</a>
            </div>
          <?php else: ?>
            <input type="hidden" name="add_restaurant" value="1">
            <h2>Add Restaurant</h2>
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group">
              <label for="restaurant_name">Name *</label>
              <input type="text" id="restaurant_name" name="restaurant_name" value="<?php echo $edit_restaurant ? htmlspecialchars($edit_restaurant['name']) : ''; ?>" required>
            </div>
            <div class="form-group">
              <label for="cuisine_type">Cuisine *</label>
              <input type="text" id="cuisine_type" name="cuisine_type" value="<?php echo $edit_restaurant ? htmlspecialchars($edit_restaurant['cuisine_type']) : ''; ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="location">Location *</label>
              <input type="text" id="location" name="location" value="<?php echo $edit_restaurant ? htmlspecialchars($edit_restaurant['location']) : ''; ?>" required>
            </div>
            <div class="form-group">
              <label for="price_range">Price Range</label>
              <select id="price_range" name="price_range">
                <?php
                $pr = $edit_restaurant ? ($edit_restaurant['price_range'] ?? '') : '';
                $options = ['₹', '₹₹', '₹₹₹', '₹₹₹₹'];
                ?>
                <option value="">Select</option>
                <?php foreach ($options as $opt): ?>
                  <option value="<?php echo $opt; ?>" <?php echo ($pr === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="phone">Phone</label>
              <input type="tel" id="phone" name="phone" maxlength="10" pattern="[0-9]{10}" placeholder="10 digits only" onkeypress="return /[0-9]/i.test(event.key)" value="<?php echo $edit_restaurant ? htmlspecialchars($edit_restaurant['phone']) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="restaurant_image">Cover Image</label>
              <input type="file" id="restaurant_image" name="restaurant_image" accept="image/*">
              <?php if ($edit_restaurant && !empty($edit_restaurant['image_url'])): ?>
                <p class="muted" style="font-size: 0.8rem; margin-top: 5px;">Current image: <a href="../<?php echo htmlspecialchars($edit_restaurant['image_url']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="open_time">Opening Time</label>
              <input type="time" id="open_time" name="open_time" value="<?php echo $edit_restaurant && !empty($edit_restaurant['open_time']) ? htmlspecialchars(substr($edit_restaurant['open_time'], 0, 5)) : ''; ?>">
            </div>
            <div class="form-group">
              <label for="close_time">Closing Time</label>
              <input type="time" id="close_time" name="close_time" value="<?php echo $edit_restaurant && !empty($edit_restaurant['close_time']) ? htmlspecialchars(substr($edit_restaurant['close_time'], 0, 5)) : ''; ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo $edit_restaurant ? htmlspecialchars($edit_restaurant['description']) : ''; ?></textarea>
          </div>

          <button type="submit" class="btn-primary">
            <?php if ($edit_restaurant): ?>
              <i class="fas fa-save"></i> Update Restaurant
            <?php else: ?>
              <i class="fas fa-plus-circle"></i> Save Restaurant
            <?php endif; ?>
          </button>
          <button type="button" id="previewRestaurantBtn" class="btn-secondary" style="margin-left: 8px;">
            <i class="fas fa-eye"></i> Preview
          </button>
        </form>
      <?php endif; ?>
      <?php if ($sessionRole === 'restaurant_owner' && (isset($restaurants) ? count($restaurants) : 0) > 0): ?>
        <form method="POST" enctype="multipart/form-data" class="card">
          <input type="hidden" name="add_menu_item" value="1">
          <h2>Add Menu Item</h2>
          <div class="form-group">
            <label for="hotel_id">Restaurant *</label>
            <select id="hotel_id" name="hotel_id" required>
              <option value="">Select restaurant</option>
              <?php foreach ($restaurants_for_menu as $restaurant): ?>
                <option value="<?php echo $restaurant['id']; ?>">
                  <?php echo htmlspecialchars($restaurant['name']); ?> (<?php echo htmlspecialchars($restaurant['cuisine_type']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="menu_name">Menu Item *</label>
            <input type="text" id="menu_name" name="menu_name" required>
          </div>

          <div class="form-group">
            <label for="price">Price (₹) *</label>
            <input type="number" id="price" name="price" min="1" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="menu_image">Menu Image</label>
            <input type="file" id="menu_image" name="menu_image" accept="image/*">
          </div>

          <div class="form-group">
            <label for="menu_description">Description</label>
            <textarea id="menu_description" name="menu_description" rows="3"></textarea>
          </div>

          <button type="submit" class="btn-primary">
            <i class="fas fa-utensils"></i>
            Save Menu Item
          </button>
          <button type="button" id="previewMenuBtn" class="btn-secondary" style="margin-left: 8px;">
            <i class="fas fa-eye"></i> Preview
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($sessionRole === 'admin'): ?>
      <!-- Admin: Restaurant Owner Data Table -->
      <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
          <h3>Restaurant Owner Data</h3>
          <p class="muted">All restaurants and their owners</p>
        </div>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                <th style="padding: 12px; text-align: left;">Restaurant Name</th>
                <th style="padding: 12px; text-align: left;">Cuisine</th>
                <th style="padding: 12px; text-align: left;">Location</th>
                <th style="padding: 12px; text-align: left;">Phone</th>
                <th style="padding: 12px; text-align: left;">Owner</th>
                <th style="padding: 12px; text-align: left;">Owner Email</th>
                <th style="padding: 12px; text-align: left;">Created</th>
                <th style="padding: 12px; text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($restaurants)): ?>
                <?php foreach ($restaurants as $restaurant): ?>
                  <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?php echo htmlspecialchars($restaurant['name']); ?></td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($restaurant['cuisine_type'] ?? 'N/A'); ?></td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($restaurant['location'] ?? 'N/A'); ?></td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($restaurant['phone'] ?? 'N/A'); ?></td>
                    <td style="padding: 12px;">
                      <?php
                      if (isset($restaurant['first_name']) && isset($restaurant['last_name'])) {
                        echo htmlspecialchars($restaurant['first_name'] . ' ' . $restaurant['last_name']);
                      } else {
                        echo 'N/A';
                      }
                      ?>
                    </td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($restaurant['owner_email'] ?? 'N/A'); ?></td>
                    <td style="padding: 12px;"><?php echo isset($restaurant['created_at']) ? date('M d, Y', strtotime($restaurant['created_at'])) : 'N/A'; ?></td>
                    <td style="padding: 12px; text-align: center;">
                      <a href="manage.php?edit_restaurant=<?php echo $restaurant['id']; ?>" style="display: inline-block; background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 4px; text-decoration: none; margin-right: 5px;">
                        <i class="fas fa-edit"></i> Edit
                      </a>
                      <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this restaurant?');">
                        <input type="hidden" name="delete_restaurant" value="1">
                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['id']; ?>">
                        <button type="submit" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" style="padding: 20px; text-align: center; color: #999;">No restaurants found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="recent-section">
      <div class="card">
        <div class="card-header">
          <h3>Latest Restaurants</h3>
          <p class="muted">Recently added entries</p>
        </div>
        <?php if (!empty($restaurants)): ?>
          <ul class="list">
            <?php foreach (array_slice($restaurants, 0, 5) as $restaurant): ?>
              <li>
                <strong><?php echo htmlspecialchars($restaurant['name']); ?></strong>
                <span><?php echo htmlspecialchars($restaurant['cuisine_type']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted empty-state">No restaurants yet.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Latest Menu Items</h3>
          <p class="muted">Preview of uploaded dishes</p>
        </div>
        <?php if (!empty($recent_menus)): ?>
          <ul class="menu-list">
            <?php foreach ($recent_menus as $item): ?>
              <li>
                <div>
                  <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                  <span class="muted"><?php echo htmlspecialchars($item['hotel_name']); ?></span>
                </div>
                <span class="price">₹<?php echo number_format($item['price'], 2); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted empty-state">No menu items recorded yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');

    if (phoneInput) {
      // Allow only numbers
      phoneInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) {
          this.value = this.value.substring(0, 10);
        }
      });

      // Prevent non-numeric keys
      phoneInput.addEventListener('keypress', function(e) {
        if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Escape', 'Enter'].includes(e.key)) {
          e.preventDefault();
        }
      });

      // Paste event handler
      phoneInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const numbersOnly = pastedText.replace(/[^0-9]/g, '').substring(0, 10);
        this.value = numbersOnly;
      });
    }
    const previewModal = document.createElement('div');
    previewModal.id = 'previewModal';
    previewModal.style.position = 'fixed';
    previewModal.style.top = '0';
    previewModal.style.left = '0';
    previewModal.style.right = '0';
    previewModal.style.bottom = '0';
    previewModal.style.background = 'rgba(0,0,0,0.4)';
    previewModal.style.display = 'none';
    previewModal.style.alignItems = 'center';
    previewModal.style.justifyContent = 'center';
    previewModal.style.zIndex = '10000';
    const modalCard = document.createElement('div');
    modalCard.style.background = '#fff';
    modalCard.style.borderRadius = '12px';
    modalCard.style.padding = '20px';
    modalCard.style.width = '90%';
    modalCard.style.maxWidth = '640px';
    const modalTitle = document.createElement('h3');
    modalTitle.id = 'previewTitle';
    const modalContent = document.createElement('div');
    modalContent.id = 'previewContent';
    modalContent.style.display = 'grid';
    modalContent.style.gap = '12px';
    const closeBtn = document.createElement('button');
    closeBtn.textContent = 'Close';
    closeBtn.style.marginTop = '12px';
    closeBtn.style.background = '#667eea';
    closeBtn.style.color = '#fff';
    closeBtn.style.border = 'none';
    closeBtn.style.padding = '8px 14px';
    closeBtn.style.borderRadius = '8px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.addEventListener('click', () => previewModal.style.display = 'none');
    modalCard.appendChild(modalTitle);
    modalCard.appendChild(modalContent);
    modalCard.appendChild(closeBtn);
    previewModal.appendChild(modalCard);
    document.body.appendChild(previewModal);

    function setPreview(title, rows) {
      modalTitle.textContent = title;
      modalContent.innerHTML = '';
      rows.forEach(([k, v]) => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.innerHTML = `<strong>${k}</strong><span>${v || ''}</span>`;
        modalContent.appendChild(row);
      });
      previewModal.style.display = 'flex';
    }
    const previewRestaurantBtn = document.getElementById('previewRestaurantBtn');
    if (previewRestaurantBtn) {
      previewRestaurantBtn.addEventListener('click', function() {
        const name = document.getElementById('restaurant_name')?.value || '';
        const cuisine = document.getElementById('cuisine_type')?.value || '';
        const location = document.getElementById('location')?.value || '';
        const priceRange = document.getElementById('price_range')?.value || '';
        const phone = document.getElementById('phone')?.value || '';
        const openTime = document.getElementById('open_time')?.value || '';
        const closeTime = document.getElementById('close_time')?.value || '';
        const desc = document.getElementById('description')?.value || '';
        setPreview('Restaurant Preview', [
          ['Name', name],
          ['Cuisine', cuisine],
          ['Location', location],
          ['Price Range', priceRange],
          ['Phone', phone],
          ['Open Time', openTime],
          ['Close Time', closeTime],
          ['Description', desc]
        ]);
      });
    }
    const previewMenuBtn = document.getElementById('previewMenuBtn');
    if (previewMenuBtn) {
      previewMenuBtn.addEventListener('click', function() {
        const hotelSel = document.getElementById('hotel_id');
        const hotel = hotelSel ? hotelSel.options[hotelSel.selectedIndex]?.text : '';
        const item = document.getElementById('menu_name')?.value || '';
        const price = document.getElementById('price')?.value || '';
        const desc = document.getElementById('menu_description')?.value || '';
        setPreview('Menu Item Preview', [
          ['Restaurant', hotel],
          ['Item', item],
          ['Price', price ? `₹${parseFloat(price).toFixed(2)}` : ''],
          ['Description', desc]
        ]);
      });
    }
  });
</script>

<?php include '../includes/footer.php'; ?>
