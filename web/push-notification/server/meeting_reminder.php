<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

error_reporting(E_ALL & ~E_WARNING);

// Load subscriptions and meetings
$subscriptions = json_decode(file_get_contents(__DIR__ . '/subscription.json'), true);
$meetings = json_decode(file_get_contents(__DIR__ . '/meetings.json'), true);

$webPush = new WebPush([
    'VAPID' => [
        'subject' => 'mailto:noreplytoyoflex@gmail.com',
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
]);

$now = new DateTime();

foreach ($meetings as &$meeting) {

    // Skip meeting if all notifications are already sent
    if ($meeting['notified']['booked'] && $meeting['notified']['before30'] && $meeting['notified']['before5']) {
        continue;
    }

    $meetingTime = new DateTime($meeting['datetime']);
    $diff = $meetingTime->getTimestamp() - $now->getTimestamp(); // seconds until meeting

    foreach ($meeting['participants'] as $email) {
        if (!isset($subscriptions[$email])) continue;

        $subData = $subscriptions[$email];
        $sub = Subscription::create([
            'endpoint' => $subData['endpoint'],
            'publicKey' => $subData['keys']['p256dh'],
            'authToken' => $subData['keys']['auth'],
        ]);

        // --- Booked notification ---
        if (!$meeting['notified']['booked']) {
            $payload = json_encode([
                'title' => '✅ Meeting Booked',
                'body'  => "{$meeting['title']} has been booked!"
            ]);
            $webPush->queueNotification($sub, $payload);
            $meeting['notified']['booked'] = true;
        }

        // --- 30 minutes before ---
        if ($diff <= 1800 && $diff > 0 && !$meeting['notified']['before30']) {
            $payload = json_encode([
                'title' => '⏰ Meeting Reminder',
                'body'  => "{$meeting['title']} starts in 30 minutes!"
            ]);
            $webPush->queueNotification($sub, $payload);
            $meeting['notified']['before30'] = true;
        }

        // --- 5 minutes before ---
        if ($diff <= 300 && $diff > 0 && !$meeting['notified']['before5']) {
            $payload = json_encode([
                'title' => '⚡ Meeting Reminder',
                'body'  => "{$meeting['title']} starts in 5 minutes!"
            ]);
            $webPush->queueNotification($sub, $payload);
            $meeting['notified']['before5'] = true;
        }
    }
}

// Flush all notifications
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getRequest()->getUri()->__toString();
    echo $report->isSuccess()
        ? "✅ Sent reminder to $endpoint\n"
        : "❌ Failed for $endpoint: {$report->getReason()}\n";
}

// Save updated flags
file_put_contents(__DIR__ . '/meetings.json', json_encode($meetings, JSON_PRETTY_PRINT));
logMessage("Script finished.\n");
?>