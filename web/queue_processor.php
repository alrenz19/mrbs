<?php
// background_email.php - Session-less version

// Bypass MRBS session initialization for background processes
define('MRBS_NO_SESSION', true);
define('MRBS_NO_DB', true);

// Minimal includes - only what's needed for email
require 'defaultincludes.inc';
require_once 'Mail/participantsNotif.php';

class BackgroundEmail {
    public static function sendBatchEmails($participants, $subject, $meetingDetails, $action) {
        self::log("Starting background email process without sessions");
        
        try {
            $email = new Email();
            $recipients = preg_split('/[\n, ]+/', $participants);
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                
                try {
                    $email->send($recipient, $subject, $meetingDetails, $action);
                    $successCount++;
                    self::log("✓ Sent to: " . $recipient);
                    usleep(100000); // 0.1 second delay
                } catch (\Exception $e) {
                    $failCount++;
                    self::log("✗ Failed: " . $recipient . " - " . $e->getMessage());
                }
            }
            
            self::log("Email batch completed. Success: $successCount, Failed: $failCount");
            return ['success' => $successCount, 'failed' => $failCount];
            
        } catch (\Exception $e) {
            self::log("Fatal error in sendBatchEmails: " . $e->getMessage());
            return ['success' => 0, 'failed' => count($recipients)];
        }
    }
    
    public static function processEmailJob($dataFile) {
        self::log("Processing job: " . $dataFile);
        
        if (!file_exists($dataFile)) {
            self::log("Job file not found: " . $dataFile);
            return false;
        }
        
        $data = json_decode(file_get_contents($dataFile), true);
        if (!$data) {
            self::log("Invalid JSON in job file");
            return false;
        }
        
        $result = self::sendBatchEmails(
            $data['participants'],
            $data['subject'],
            $data['meetingDetails'],
            $data['action']
        );
        
        // Clean up
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }
        
        return $result;
    }
    
    private static function log($message) {
        $logFile = __DIR__ . '/logs/email_background.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Also echo if running in CLI
        if (PHP_SAPI === 'cli') {
            echo $logEntry;
        }
    }
}

// CLI execution only
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    BackgroundEmail::processEmailJob($argv[1]);
} else {
    die("This script can only be run via command line.");
}
?>