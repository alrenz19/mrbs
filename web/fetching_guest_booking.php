<?php
namespace MRBS;

require "defaultincludes.inc";

// Cache settings
$cacheFile = __DIR__ . '/cache/fetching_guest_booking_cache.json';
$cacheTTL = 15; // seconds (adjust as needed)

// Function to get cached data if valid
function getCache($cacheFile, $cacheTTL) {
    if (!file_exists($cacheFile)) return false;

    $fileTime = filemtime($cacheFile);
    if (($fileTime + $cacheTTL) < time()) {
        // Cache expired
        return false;
    }

    $content = file_get_contents($cacheFile);
    if (!$content) return false;

    return json_decode($content, true);
}

// Try to get cached data
$data = getCache($cacheFile, $cacheTTL);

if (!$data) {
    // Cache miss or expired — run the DB query

    $sql =  "SELECT 
        e.id AS entry_id,
        creator.display_name AS creator_name,
        r.room_name,
        a.area_name,
        e.name,
        e.description,
        CASE 
            WHEN e.start_time >= UNIX_TIMESTAMP(CURDATE()) 
                AND e.start_time < UNIX_TIMESTAMP(CURDATE() + INTERVAL 1 DAY)
                THEN CONCAT(
                    DATE_FORMAT(FROM_UNIXTIME(e.start_time), '%h:%i %p'),
                    ' to ',
                    DATE_FORMAT(FROM_UNIXTIME(e.end_time), '%h:%i %p')
                )
            ELSE CONCAT(
                DATE_FORMAT(FROM_UNIXTIME(e.start_time), '%Y-%m-%d %h:%i %p'),
                ' to ',
                DATE_FORMAT(FROM_UNIXTIME(e.end_time), '%h:%i %p')
            )
        END AS reservation_time,
        p.participants,
        g.guest_participants,
        CASE 
            WHEN e.start_time >= UNIX_TIMESTAMP(CURDATE()) 
                AND e.start_time < UNIX_TIMESTAMP(CURDATE() + INTERVAL 1 DAY)
                THEN 'today_reservation'
            ELSE 'upcoming_reservation'
        END AS reservation_group

    FROM mrbs_entry AS e

    JOIN mrbs_users AS creator ON creator.name = e.create_by
    JOIN mrbs_room AS r ON e.room_id = r.id
    JOIN mrbs_area AS a ON r.area_id = a.id

    -- Participants aggregation (valid emails)
    JOIN (
        SELECT 
            mg.entry_id,
            GROUP_CONCAT(DISTINCT u.display_name ORDER BY u.display_name SEPARATOR ', ') AS participants
        FROM mrbs_groups AS mg
        JOIN mrbs_users AS u ON u.email = mg.email
        WHERE mg.email IS NOT NULL AND mg.email != ''
        GROUP BY mg.entry_id
    ) p ON p.entry_id = e.id

    -- Guest participants aggregation (empty or NULL emails)
    JOIN (
        SELECT 
            entry_id,
            GROUP_CONCAT(DISTINCT full_name ORDER BY full_name SEPARATOR ', ') AS guest_participants
        FROM mrbs_groups
        WHERE (email IS NULL OR email = '') 
        AND full_name IS NOT NULL 
        AND full_name != ''
        GROUP BY entry_id
    ) g ON g.entry_id = e.id

    WHERE e.entry_type = 0 
    AND e.start_time >= UNIX_TIMESTAMP(CURDATE())
    AND e.start_time < UNIX_TIMESTAMP(DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01'))

    ORDER BY reservation_group, e.start_time;



    ";

    $res = db()->sql_query($sql);

    $today = [];
    $tomorrow = [];
    $doneMeeting = [];
    $currentTime = time();

    foreach ($res as $row) {
        $timeRange = $row['reservation_time'] ?? '';
        $times = preg_split('/\s*to\s*/i', $timeRange);

        if (count($times) !== 2) {
            continue; // Skip invalid format
        }

        $startStr = $times[0];
        $endStr = $times[1];
        $status_reservation = $row['reservation_group'] ?? 'upcoming_reservation';

        if ($status_reservation === 'today_reservation') {
            $todayDate = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $startStr)) {
                $startStr = $todayDate . ' ' . $startStr;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $endStr)) {
                $endStr = $todayDate . ' ' . $endStr;
            }
        }

        $startTime = strtotime($startStr);
        $endTime = strtotime($endStr);

        $participants = array_filter(array_map('trim', explode(',', $row['participants'] ?? '')));

        $status_meeting = 'upcoming'; // default

        if ($status_reservation === 'today_reservation') {
            if ($currentTime > $endTime) {
                $status_meeting = 'done';
            } elseif ($currentTime >= $startTime && $currentTime <= $endTime) {
                $status_meeting = 'inprogress';
            } else {
                $status_meeting = 'upcoming';
            }
        } else {
            $status_meeting = 'upcoming';
        }

        $dateStr = ($status_reservation !== 'today_reservation') ? date('M d, Y', $startTime) : '';

        $entry = [
            'guestName' => $row['guest_participants'] ?? '',
            'meetingTitle' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'creator' => $row['creator_name'] ?? '',
            'status' => $status_meeting,
            'date' => $dateStr,
            'time' => $timeRange,
            'room' => ($row['room_name'] ?? 'No room yet') . '-' . ($row['area_name'] ?? ''),
            'participants' => array_values($participants),
        ];

        if ($status_meeting === 'done' && $status_reservation === 'today_reservation') {
            $doneMeeting[] = $entry;
        } elseif ($status_reservation === 'today_reservation' && $status_meeting !== 'done') {
            $today[] = $entry;
        } else {
            $tomorrow[] = $entry;
        }
    }

    $data = [
        'today' => $today,
        'tomorrow' => $tomorrow,
        'done' => $doneMeeting,
    ];

    // Save to cache file as JSON
    file_put_contents($cacheFile, json_encode($data));
}

// Calculate ETag based on cached data (or fresh data)
$etag = '"' . md5(json_encode($data)) . '"';

// Send ETag header
header("ETag: $etag");

// Check for If-None-Match header to respond 304 if match
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    // Data not modified
    http_response_code(304);
    exit;
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($data);
exit;
