<?php
// Path to subscription.json
$file = __DIR__ . '/subscription.json';

// Read incoming JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Validate payload
if (!$data || !isset($data['subscription']) || !isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid payload"]);
    error_log("save_subscription.php: Invalid payload: " . $raw);
    exit;
}

$email = $data['email'];
$subscription = $data['subscription'];

// Ensure subscription.json exists and is writable
if (!file_exists($file)) {
    if (file_put_contents($file, "{}") === false) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create subscription.json"]);
        error_log("save_subscription.php: Failed to create $file");
        exit;
    }
}

// Load existing subscriptions
$subscriptions = json_decode(file_get_contents($file), true);
if (!is_array($subscriptions)) {
    $subscriptions = [];
}

// Save or update subscription by email
$subscriptions[$email] = $subscription;

// Write back to file
if (file_put_contents($file, json_encode($subscriptions, JSON_PRETTY_PRINT)) === false) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to write subscription.json"]);
    error_log("save_subscription.php: Failed to write $file");
    exit;
}

// Success
echo json_encode(["success" => true, "message" => "Subscription saved"]);
?>
