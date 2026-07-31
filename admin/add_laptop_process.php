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
    $stmt->bind_param('iissdissssss s',
        $brand_id, $category_id, $model, $title, $price, $stock,
        $specs_json, $processor, $ram, $storage, $gpu, $display, $image_url
    );
    $stmt->close();

    // Use mysqli_query to avoid bind_param count confusion
    $bran  = $conn->real_escape_string((string)$brand_id);
    $catg  = $conn->real_escape_string((string)$category_id);
    $ttl   = $conn->real_escape_string($title);
    $mdl   = $conn->real_escape_string($model);
    $prc   = $conn->real_escape_string((string)$price);
    $stk   = $conn->real_escape_string((string)$stock);
    $spc   = $conn->real_escape_string($specs_json);
    $proc  = $conn->real_escape_string($processor);
    $rmm   = $conn->real_escape_string($ram);
    $stor  = $conn->real_escape_string($storage);
    $gpuu  = $conn->real_escape_string($gpu);
    $disp  = $conn->real_escape_string($display);

    $insert_sql = "INSERT INTO laptop
        (brand_id, category_id, model, title, price, stock, specs, processor, ram, storage, gpu, display, image_url, created_at)
        VALUES ($bran, $catg, '$mdl', '$ttl', $prc, $stk, '$spc', '$proc', '$rmm', '$stor', '$gpuu', '$disp', '', NOW())";

    if ($conn->query($insert_sql)) {
        $laptop_id = $conn->insert_id;

        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Handle uploaded gallery image files
        if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
            foreach ($_FILES['gallery_images']['name'] as $key => $filename) {
                if (!empty($filename) && ($_FILES['gallery_images']['error'][$key] ?? 0) === 0 && is_uploaded_file($_FILES['gallery_images']['tmp_name'][$key] ?? '')) {
                    $image_ext    = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $new_img_name = uniqid('laptop_', true) . '.' . $image_ext;
                    $destination  = $upload_dir . $new_img_name;
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $destination)) {
                        $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                        $img_stmt->bind_param('is', $laptop_id, $new_img_name);
                        $img_stmt->execute();
                        $img_stmt->close();
                    }
                }
            }
        }

        // Handle direct image URLs
        if (!empty($_POST['gallery_urls'])) {
            $urls = preg_split('/\r\n|\r|\n/', trim($_POST['gallery_urls']));
            foreach ($urls as $url) {
                $clean_url = trim($url);
                if ($clean_url !== '') {
                    $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                    $img_stmt->bind_param('is', $laptop_id, $clean_url);
                    $img_stmt->execute();
                    $img_stmt->close();
                }
            }
        }

        header('Location: inventory.php?success=1');
        exit();
    }

    header('Location: inventory.php?error=1');
    exit();
}

header('Location: inventory.php');
exit();