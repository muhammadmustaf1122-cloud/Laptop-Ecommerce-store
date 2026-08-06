<?php
session_start();
include '../login/auth.php';
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laptop'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        header('Location: inventory.php?error=csrf');
        exit();
    }

    $title       = trim($_POST['title']       ?? '');
    $model       = trim($_POST['model']       ?? '');
    $brand_id    = (int) ($_POST['brand_id']    ?? 0);
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $price       = (float) ($_POST['price']     ?? 0);
    $stock       = (int) ($_POST['stock']       ?? 0);

    if ($title === '' || $model === '' || $brand_id <= 0 || $category_id <= 0 || $price <= 0 || $stock < 0) {
        header('Location: inventory.php?error=invalid');
        exit();
    }

    // Flat spec columns (new schema)
    $processor = trim($_POST['processor'] ?? '');
    $ram       = trim($_POST['ram']       ?? '');
    $storage   = trim($_POST['storage']   ?? '');
    $gpu       = trim($_POST['gpu']       ?? '');
    $display   = trim($_POST['display']   ?? '');

    // Keep JSON blob for any legacy code still reading it
    $specs_json = json_encode(compact('processor', 'ram', 'storage', 'gpu', 'display'));
    $image_url  = '';

    // 13 columns: brand_id(i), category_id(i), model(s), title(s), price(d), stock(i),
    //             specs(s), processor(s), ram(s), storage(s), gpu(s), display(s), image_url(s)
    $stmt = $conn->prepare(
        'INSERT INTO laptop
            (brand_id, category_id, model, title, price, stock, specs, processor, ram, storage, gpu, display, image_url, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('iissdisssssss',
        $brand_id, $category_id, $model, $title, $price, $stock,
        $specs_json, $processor, $ram, $storage, $gpu, $display, $image_url
    );

    if ($stmt->execute()) {
        $laptop_id = $conn->insert_id;
        $stmt->close();

        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB limit

        // Handle uploaded gallery image files
        if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
            foreach ($_FILES['gallery_images']['name'] as $key => $filename) {
                $tmp_name = $_FILES['gallery_images']['tmp_name'][$key] ?? '';
                $file_size = $_FILES['gallery_images']['size'][$key] ?? 0;
                $file_err  = $_FILES['gallery_images']['error'][$key] ?? 1;

                if (!empty($filename) && $file_err === 0 && is_uploaded_file($tmp_name) && $file_size <= $max_size) {
                    $image_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($image_ext, $allowed_exts, true)) {
                        $check_img = @getimagesize($tmp_name);
                        if ($check_img !== false && in_array($check_img['mime'], $allowed_mimes, true)) {
                            $new_img_name = 'laptop_' . bin2hex(random_bytes(8)) . '.' . $image_ext;
                            $destination  = $upload_dir . $new_img_name;
                            if (move_uploaded_file($tmp_name, $destination)) {
                                $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                                $img_stmt->bind_param('is', $laptop_id, $new_img_name);
                                $img_stmt->execute();
                                $img_stmt->close();
                            }
                        }
                    }
                }
            }
        }

        // Handle direct image URLs
        if (!empty($_POST['gallery_urls'])) {
            $urls = preg_split('/\r\n|\r|\n/', trim($_POST['gallery_urls']));
            foreach ($urls as $url) {
                $clean_url = trim($url);
                if ($clean_url !== '' && filter_var($clean_url, FILTER_VALIDATE_URL)) {
                    $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                    $img_stmt->bind_param('is', $laptop_id, $clean_url);
                    $img_stmt->execute();
                    $img_stmt->close();
                }
            }
        }

        header('Location: inventory.php?success=1');
        exit();
    } else {
        $stmt->close();
    }

    header('Location: inventory.php?error=1');
    exit();
}

header('Location: inventory.php');
exit();