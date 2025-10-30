<?php
// background_email.php - Debug version
require_once 'Mail/participantsNotif.php';

class BackgroundEmail {
    public static function processAllQueuedEmails() {
        self::log("Starting email queue processing");
        
        $queueDir = __DIR__ . '/temp/email_queue';
        if (!is_dir($queueDir)) {
            self::log("Queue directory doesn't exist");
            return;
        }
        
        $files = glob($queueDir . '/email_job_*.json');
        $processed = 0;
        $failed = 0;
        
        foreach ($files as $file) {
            try {
                self::log("Processing: " . basename($file));
                $result = self::processEmailJob($file);
                
                if ($result === true) {
                    $processed++;
                    self::log("✅ Successfully processed: " . basename($file));
                } else {
                    $failed++;
                    self::log("❌ Failed to process: " . basename($file));
                }
            } catch (Exception $e) {
                $failed++;
                self::log("💥 Error processing " . basename($file) . ": " . $e->getMessage());
            }
        }
        
        self::log("Completed: $processed processed, $failed failed");
    }
    
    public static function processEmailJob($dataFile) {
        if (!file_exists($dataFile)) {
            self::log("❌ Job file not found");
            return false;
        }
        
        $data = json_decode(file_get_contents($dataFile), true);
        if (!$data || !isset($data['participants'])) {
            self::log("❌ Invalid JSON data");
            unlink($dataFile);
            return false;
        }
        
        try {
            self::log("📧 Creating Email instance...");
            $email = new Email();
            
            $recipients = preg_split('/[\n, ]+/', $data['participants']);
            self::log("📨 Recipients: " . implode(', ', $recipients));
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient)) continue;
                
                try {
                    self::log("🔄 Sending to: " . $recipient);
                    $result = $email->send($recipient, $data['subject'], $data['meetingDetails'], $data['action']);
                    
                    if ($result) {
                        $successCount++;
                        self::log("✅ Sent to: " . $recipient);
                    } else {
                        $failCount++;
                        self::log("❌ Failed to send to: " . $recipient . " (returned false)");
                    }
                    
                    usleep(50000);
                } catch (Exception $e) {
                    $failCount++;
                    self::log("💥 Exception sending to " . $recipient . ": " . $e->getMessage());
                }
            }
            
            if ($successCount > 0) {
                unlink($dataFile);
                self::log("🎉 Job completed and deleted: $successCount sent, $failCount failed");
            } else {
                self::log("⚠️  Job not deleted (all failed): $successCount sent, $failCount failed");
            }
            
            return $successCount > 0;
            
        } catch (Exception $e) {
            self::log("💥 Fatal error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function log($message) {
        $logFile = __DIR__ . '/logs/email_processor.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        if (PHP_SAPI === 'cli') echo $logEntry;
    }
}

if (PHP_SAPI === 'cli') {
    BackgroundEmail::processAllQueuedEmails();
} else {
    http_response_code(403);
    die("Access denied");
}