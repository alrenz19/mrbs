<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// --- Paths ---
$meetingsPath = __DIR__ . '/meetings.json';
$subscriptionsPath = __DIR__ . '/subscription.json';
$logPath = __DIR__ . '/meeting_reminder.log';

// --- Logging ---
function logMessage($msg) {
    global $logPath;
    $time = (new DateTime())->format('Y-m-d H:i:s');
    file_put_contents($logPath, "[$time] $msg\n", FILE_APPEND);
}

// --- Load subscriptions ---
function loadSubscriptions() {
    global $subscriptionsPath;
    if (!file_exists($subscriptionsPath)) return [];
    return json_decode(file_get_contents($subscriptionsPath), true);
}

// --- Load meetings ---
function loadMeetings() {
    global $meetingsPath;
    if (!file_exists($meetingsPath)) return [];
    return json_decode(file_get_contents($meetingsPath), true);
}

// --- Save meetings ---
function saveMeetings($meetings) {
    global $meetingsPath;
    file_put_contents($meetingsPath, json_encode($meetings, JSON_PRETTY_PRINT));
}

// --- Initialize WebPush ---
$webPush = new WebPush([
    'VAPID' => [
        'subject' => 'mailto:noreplytoyoflex@gmail.com',
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
]);

logMessage("Daemon started.");

// --- Infinite loop ---
while (true) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $subscriptions = loadSubscriptions();
    $meetings = loadMeetings();

    foreach ($meetings as &$meeting) {
        // Try to parse datetime
        $meetingTime = DateTime::createFromFormat(
            'l, F d, Y \a\t g:i A',
            $meeting['datetime'],
            new DateTimeZone('Asia/Manila')
        );

        // Fallback to strtotime if createFromFormat fails
        if (!$meetingTime) {
            $timestamp = strtotime($meeting['datetime']);
            if ($timestamp === false) {
                logMessage("❌ Failed to parse meeting datetime: " . $meeting['datetime']);
                continue;
            }
            $meetingTime = new DateTime();
            $meetingTime->setTimestamp($timestamp);
            $meetingTime->setTimezone(new DateTimeZone('Asia/Manila'));
        }

        // Only notify if the meeting date is today
        if ($meetingTime->format('Y-m-d') !== $now->format('Y-m-d')) continue;

        // Skip fully notified meetings
        if ($meeting['notified']['booked'] && $meeting['notified']['before30'] && $meeting['notified']['before5']) continue;

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

    // Flush notifications
    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getRequest()->getUri()->__toString();
        $msg = $report->isSuccess()
            ? "✅ Sent reminder to $endpoint"
            : "❌ Failed for $endpoint: {$report->getReason()}";
        logMessage($msg);
    }

    // Save updated meetings
    saveMeetings($meetings);

    // Wait 60 seconds
    sleep(60);
}
