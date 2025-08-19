<?php
namespace MRBS;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

error_reporting(E_ALL & ~E_WARNING);


// Load subscriptions JSON
$subscriptions = json_decode(file_get_contents(__DIR__ . '/subscription.json'), true) ?? [];
$meetings = json_decode(file_get_contents(__DIR__ . '/meetings.json'), true) ?? []; 

function sendPushNotification($emails, $title, $body, $start_time) {
    global $subscriptions, $meetings;

    $meeting = [
        'id' => uniqid(),
        'title' => $title,
        'datetime' => time_date_string($start_time),
        'participants' => $emails,
        'notified' => [
            'booked' => true,
            'before30' => false,
            'before5' => false
        ]
    ];
    $meetings[] = $meeting;
    $webPush = new WebPush([
        'VAPID' => [
            'subject'    => 'mailto:noreplytoyoflex@gmail.com',
            'publicKey'  => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ]);

    foreach ($emails as $email) {
        if (!isset($subscriptions[$email])) {
            continue; // skip if no subscription for this email
        }

        $subData = $subscriptions[$email];
        $sub = Subscription::create([
            'endpoint'  => $subData['endpoint'],
            'publicKey' => $subData['keys']['p256dh'],
            'authToken' => $subData['keys']['auth'],
        ]);

        $formatBody = $body . "\n" . "at " . time_date_string($start_time);
        // Queue notification
        $webPush->queueNotification($sub, json_encode([
            'title' => $title,
            'body'  => $formatBody,
        ]));
    }

    // Flush once for all queued notifications
    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getRequest()->getUri()->__toString();
        echo $report->isSuccess()
            ? "✅ Push sent to $endpoint\n"
            : "❌ Push failed for $endpoint: {$report->getReason()}\n";
    }

    file_put_contents(__DIR__ . '/meetings.json', json_encode($meetings, JSON_PRETTY_PRINT));
}
?>
