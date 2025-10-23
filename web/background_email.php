<?php
namespace MRBS;

/**
 * Detect PHP path for Synology NAS compatibility
 */
// function detectPhpPath() {
//     $possiblePaths = [
//         '/usr/bin/php',          // Standard Linux
//         '/usr/local/bin/php',    // Alternative Linux
//         '/opt/bin/php',          // Synology sometimes
//         '/var/packages/PHP/target/bin/php', // Synology PHP package
//         'php'                    // Fallback to PATH
//     ];
    
//     foreach ($possiblePaths as $path) {
//         if (is_executable($path)) {
//             return $path;
//         }
//     }
    
//     // Test if php is in PATH
//     $output = [];
//     exec('which php 2>/dev/null', $output, $returnCode);
//     if ($returnCode === 0 && !empty($output[0])) {
//         return $output[0];
//     }
    
//     // Final fallback
//     return 'php';
// }

require 'defaultincludes.inc';
require_once 'Mail/participantsNotif.php';

class BackgroundEmail {
    public static function sendBatchEmails($participants, $subject, $meetingDetails, $action) {
        self::log("NAS: Background email started for: " . $subject);
        
        $email = new Email();
        $recipients = preg_split('/[\n, ]+/', $participants);
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }
            
            try {
                $email->send($recipient, $subject, $meetingDetails, $action);
                $successCount++;
                self::log("NAS: ✓ Sent to: " . $recipient);
                
                // Smaller delay for NAS efficiency
                usleep(50000); // 0.05 seconds
            } catch (\Exception $e) {
                $failCount++;
                self::log("NAS: ✗ Failed: " . $recipient . " - " . $e->getMessage());
            }
        }
        
        self::log("NAS: Email batch completed. Success: $successCount, Failed: $failCount");
    }
    
    /**
     * NAS-optimized file-based job processing
     */
    public static function processEmailJob($dataFile) {
        if (!file_exists($dataFile)) {
            self::log("NAS: Job file not found: " . $dataFile);
            return;
        }
        
        $data = json_decode(file_get_contents($dataFile), true);
        if (!$data) {
            self::log("NAS: Invalid job data in: " . $dataFile);
            return;
        }
        
        self::log("NAS: Processing job: " . $dataFile);
        self::sendBatchEmails(
            $data['participants'],
            $data['subject'],
            $data['meetingDetails'],
            $data['action']
        );
        
        // Clean up job file
        unlink($dataFile);
        self::log("NAS: Job completed and cleaned up: " . $dataFile);
    }
    
    private static function log($message) {
        $logFile = __DIR__ . '/logs/email_background.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Handle both direct and file-based execution for NAS
if (PHP_SAPI === 'cli') {
    if (isset($argv[1])) {
        // File-based execution (better for NAS)
        if (file_exists($argv[1]) && pathinfo($argv[1], PATHINFO_EXTENSION) === 'json') {
            BackgroundEmail::processEmailJob($argv[1]);
        } 
        // Direct parameter execution (fallback)
        else if (isset($argv[4])) {
            $participants = $argv[1];
            $subject = $argv[2];
            $meetingDetails = json_decode($argv[3], true);
            $action = $argv[4];
            
            BackgroundEmail::sendBatchEmails($participants, $subject, $meetingDetails, $action);
        }
    }
}
?>