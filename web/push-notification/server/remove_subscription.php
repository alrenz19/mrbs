<?php
// remove_subscription.php
$file = __DIR__ . '/subscription.json';
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid payload"]);
    exit;
}

$email = $data['email'];

if (file_exists($file)) {
    $subscriptions = json_decode(file_get_contents($file), true);
    if (isset($subscriptions[$email])) {
        unset($subscriptions[$email]);
        file_put_contents($file, json_encode($subscriptions, JSON_PRETTY_PRINT));
    }
}

echo json_encode(["success" => true, "message" => "Subscription removed"]);
?>
