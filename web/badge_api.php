<?php
namespace MRBS;

require "defaultincludes.inc";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Count new/upcoming meetings for badge
    $badge_sql = "SELECT COUNT(*) as badge_count 
                  FROM mrbs_entry AS e 
                  WHERE e.entry_type = 0 
                  AND DATE(FROM_UNIXTIME(e.start_time)) >= CURDATE()
                  AND e.start_time > UNIX_TIMESTAMP()";
    
    $badge_count = db()->query1($badge_sql);
    
    // Also count meetings created in the last 24 hours as "new"
    $new_meetings_sql = "SELECT COUNT(*) as new_count 
                         FROM mrbs_entry AS e 
                         WHERE e.entry_type = 0 
                         AND e.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $new_count = db()->query1($new_meetings_sql);
    
    $total_badge_count = max($badge_count, $new_count);
    
    echo json_encode([
        'badge_count' => $total_badge_count,
        'last_updated' => date('Y-m-d H:i:s'),
        'status' => 'success'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'badge_count' => 0,
        'last_updated' => date('Y-m-d H:i:s'),
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>