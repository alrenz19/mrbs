<?php
// queue_manager.php - Web-based queue management
namespace MRBS;

require 'defaultincludes.inc';

class QueueManager {
    public static function getStatus() {
        $queueDir = __DIR__ . '/temp/email_queue';
        $processingDir = __DIR__ . '/temp/email_processing';
        $logFile = __DIR__ . '/logs/email_queue.log';
        
        $queued = is_dir($queueDir) ? count(glob($queueDir . '/*.json')) : 0;
        $processing = is_dir($processingDir) ? count(glob($processingDir . '/*.json')) : 0;
        
        $lastLog = '';
        if (file_exists($logFile)) {
            $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLog = end($logs) ?: 'No logs found';
        }
        
        return [
            'queued' => $queued,
            'processing' => $processing,
            'last_activity' => $lastLog,
            'service_running' => self::isServiceRunning()
        ];
    }
    
    private static function isServiceRunning() {
        $output = [];
        exec('sc query MRBSEmailQueue 2>&1', $output, $returnCode);
        return strpos(implode(' ', $output), 'RUNNING') !== false;
    }
}

// Web interface
if (!checkAuthorised(this_page())) {
    showAccessDenied();
    exit;
}

$status = QueueManager::getStatus();

print_header();
echo "<h2>Email Queue Status</h2>";
echo "<div class='queue-status'>";
echo "<p><strong>📥 Queued Jobs:</strong> " . $status['queued'] . "</p>";
echo "<p><strong>🔄 Processing Jobs:</strong> " . $status['processing'] . "</p>";
echo "<p><strong>🔧 Service Status:</strong> " . ($status['service_running'] ? '✅ Running' : '❌ Stopped') . "</p>";
echo "<p><strong>📝 Last Activity:</strong> " . htmlspecialchars($status['last_activity']) . "</p>";
echo "</div>";

// Add manual process button for testing
echo "<form method='post' style='margin-top: 20px;'>";
echo "<input type='submit' name='process_now' value='Process Queue Now' class='submit'>";
echo "</form>";

if (isset($_POST['process_now'])) {
    // Run one iteration of queue processing
    exec('D:\PHP\php.exe queue_processor.php start > NUL 2>&1 &');
    echo "<p>Queue processing triggered...</p>";
    echo "<meta http-equiv='refresh' content='2'>";
}

print_footer();
?>