<?php
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($envLines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if ($name !== '') {
            putenv($name . '=' . $value);
        }
    }
}

session_start();
require('admin/db.php');

// If you used the manual ZIP download method, make sure this path points to your stripe-php folder:
require_once('stripe-php/init.php');

// Set your test secret API key from your Stripe Dashboard
$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: 'YOUR_STRIPE_SECRET_KEY';
\Stripe\Stripe::setApiKey($stripeSecretKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $laptop_id = isset($_POST['laptop_id']) ? intval($_POST['laptop_id']) : 0;

    // Fetch laptop details from database
    $query = "SELECT * FROM laptop WHERE id = $laptop_id LIMIT 1";
    $result = mysqli_query($conn, $query);
    $laptop = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;

    if (!$laptop) {
        header("Location: user/index.php");
        exit();
    }

    $product_name = $laptop['title'];
    $unit_amount = intval($laptop['price'] * 100); // Stripe expects amounts in cents (e.g., $10.00 = 1000)

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data' => [
                        'name' => $product_name,
                    ],
                    'unit_amount'  => $unit_amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            // Appended laptop_id here so success.php captures it for tracking
            'success_url' => 'http://localhost/laptop/success.php?session_id={CHECKOUT_SESSION_ID}&laptop_id=' . $laptop_id,
            'cancel_url'  => 'http://localhost/laptop/cancel.php',
        ]);

        // Redirect customer to Stripe Checkout page
        header("HTTP/1.1 303 See Other");
        header("Location: " . $session->url);
        exit();

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>