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

$restaurant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($restaurant_id <= 0) {
  header('Location: manage.php');
  exit;
}

$success_message = '';
$error_message = '';

// Fetch restaurant details
$stmt = $mysqli->prepare("SELECT * FROM hotels WHERE id = ?");
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$result = $stmt->get_result();
$restaurant = $result->fetch_assoc();
$stmt->close();

if (!$restaurant) {
  die('Restaurant not found.');
}

// Check permission: Admin can edit any, Owner can only edit their own
if ($sessionRole !== 'admin') {
    // Get owner ID
    $owner_email = $_SESSION['email'];
    $owner_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $owner_stmt->bind_param("s", $owner_email);
    $owner_stmt->execute();
    $owner_result = $owner_stmt->get_result();
    $owner = $owner_result->fetch_assoc();
    $owner_id = $owner ? $owner['id'] : 0;
    $owner_stmt->close();

    if ($restaurant['owner_id'] !== $owner_id) {
        die('You do not have permission to edit this restaurant.');
    }
}

function upload_image_edit(array $file, string $folder): ?string {
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
  $name = trim($_POST['restaurant_name'] ?? '');
  $cuisine = trim($_POST['cuisine_type'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $price_range = trim($_POST['price_range'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($name === '' || $cuisine === '' || $location === '') {
    $error_message = 'Please fill in all required fields.';
  } elseif (!empty($phone) && (!preg_match('/^[0-9]{10}$/', $phone))) {
    $error_message = 'Phone number must be exactly 10 digits.';
  } else {
    try {
      $image_path = $restaurant['image_url']; // Keep existing image by default
      if (!empty($_FILES['restaurant_image']['name'])) {
        $image_path = upload_image_edit($_FILES['restaurant_image'], 'restaurants');
      }

      $update_stmt = $mysqli->prepare("UPDATE hotels SET name = ?, description = ?, cuisine_type = ?, location = ?, phone = ?, price_range = ?, image_url = ?, updated_at = NOW() WHERE id = ?");
      $update_stmt->bind_param(
        "sssssssi",
        $name,
        $description,
        $cuisine,
        $location,
        $phone,
        $price_range,
        $image_path,
        $restaurant_id
      );

      if ($update_stmt->execute()) {
        $success_message = 'Restaurant updated successfully.';
        // Refresh data
        $restaurant['name'] = $name;
        $restaurant['description'] = $description;
        $restaurant['cuisine_type'] = $cuisine;
        $restaurant['location'] = $location;
        $restaurant['phone'] = $phone;
        $restaurant['price_range'] = $price_range;
        $restaurant['image_url'] = $image_path;
      } else {
        $error_message = 'Failed to update restaurant.';
      }
      $update_stmt->close();
    } catch (Throwable $th) {
      $error_message = $th->getMessage();
    }
  }
}

$page_title = 'Edit Restaurant | EatEase';
include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/manage-restaurants.css">

<div class="container" style="padding-top: 40px; padding-bottom: 40px; max-width: 800px;">
  <div class="page-header">
    <div>
        <a href="manage.php" style="display: inline-block; margin-bottom: 10px; color: #666; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Manage
        </a>
      <h1>Edit Restaurant</h1>
      <p class="muted">Update restaurant details</p>
    </div>
  </div>

  <?php if ($error_message): ?>
    <div class="alert alert-error" style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
      <?php echo htmlspecialchars($error_message); ?>
    </div>
  <?php endif; ?>

  <?php if ($success_message): ?>
    <div class="alert alert-success" style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
      <?php echo htmlspecialchars($success_message); ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="card" style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="restaurant_name" style="font-weight: 500;">Name *</label>
        <input type="text" id="restaurant_name" name="restaurant_name" value="<?php echo htmlspecialchars($restaurant['name']); ?>" required style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px;">
      </div>
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="cuisine_type" style="font-weight: 500;">Cuisine *</label>
        <input type="text" id="cuisine_type" name="cuisine_type" value="<?php echo htmlspecialchars($restaurant['cuisine_type']); ?>" required style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px;">
      </div>
    </div>

    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="location" style="font-weight: 500;">Location *</label>
        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($restaurant['location']); ?>" required style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px;">
      </div>
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="price_range" style="font-weight: 500;">Price Range</label>
        <input type="text" id="price_range" name="price_range" value="<?php echo htmlspecialchars($restaurant['price_range']); ?>" maxlength="5" pattern="[0-9]{1,5}" placeholder=" " onkeypress="return /[0-9]/i.test(event.key)" style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px;">
      </div>
    </div>

    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="phone" style="font-weight: 500;">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($restaurant['phone']); ?>" maxlength="10" pattern="[0-9]{10}" placeholder="10 digits only" onkeypress="return /[0-9]/i.test(event.key)" style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px;">
      </div>
      <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <label for="restaurant_image" style="font-weight: 500;">Cover Image</label>
        <input type="file" id="restaurant_image" name="restaurant_image" accept="image/*" style="padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 6px;">
        <?php if ($restaurant['image_url']): ?>
            <div style="margin-top: 5px;">
                <small>Current Image:</small><br>
                <img src="../<?php echo htmlspecialchars($restaurant['image_url']); ?>" alt="Current" style="height: 50px; border-radius: 4px;">
            </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
      <label for="description" style="font-weight: 500;">Description</label>
      <textarea id="description" name="description" rows="4" style="padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; resize: vertical;"><?php echo htmlspecialchars($restaurant['description']); ?></textarea>
    </div>

    <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 500; cursor: pointer; width: 100%;">
      Update Restaurant
    </button>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const phoneInput = document.getElementById('phone');
  
  if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '');
      if (this.value.length > 10) {
        this.value = this.value.substring(0, 10);
      }
    });
  }
});
</script>

<?php include '../includes/footer.php'; ?>