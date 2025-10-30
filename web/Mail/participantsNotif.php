<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'lib/vendor/autoload.php';

class Email
{
    private PHPMailer $mail;
    private $db;

    public function __construct()
    {
        // Initialize PHPMailer
        $this->mail = new PHPMailer(true);
        $this->db = $this->connectDB();

        // Configure SMTP
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.office365.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'tfcbookingsystem.noreply.ph@toyoflex.com';
        $this->mail->Password   = '111111';
        // $this->mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
        // $this->mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
         //$this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        $this->mail->setFrom('tfcbookingsystem.noreply.ph@toyoflex.com', 'Meeting Reminder');
        $this->mail->isHTML(true);
    }

    private function connectDB() {
        try {
            $host = 'localhost';
            $dbname = 'mrbs'; // Your database name
            $username = 'mrbsuser'; // Your username
            $password = 'MrbsPassword123!'; // Your password
            
            $db = new \PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]);
            return $db;
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function send(string $to, string $subject, array $meetingDetails, string $action = ''): bool
    {
        try {
            $body = null;
            if ($action === 'cancelled') {
              $body = $this->buildCancelMeetingBody($meetingDetails);
            } else {
              $body = $this->buildMeetingBody($meetingDetails);
              $entry_id = $meetingDetails['entry_id'];
              $existingId = $meetingDetails['id'];
              $this->saveRepresentative($entry_id, $to, $existingId);
            }

            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            $this->mail->send();     
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function buildCancelMeetingBody(array $details): string
    {
        $subject     = htmlspecialchars($details['name']);
        $start_time = $this->timeDateString($details['start_time']);
        $end_time = $this->timeDateString($details['end_time']);
        $organizer = $this->getOrganizer($details['created_by']);
        
        return <<<HTML
        <html>
          <head>
            <style>
              body { font-family: Arial, sans-serif; color: #333; }
              .container { padding: 20px; border: 1px solid #e0e0e0; background-color: #f9f9f9; max-width: 600px; margin: auto; }
              h2 { color: #2c3e50; }
              .details { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin-top: 15px; }
              .footer { font-size: 12px; color: #888; margin-top: 20px; }
            </style>
          </head>
          <body>
            <div class="container">
              <h2>Meeting Canceled</h2>
              <p>Good day</p>
              <p>The following meeting has been <strong>canceled</strong> by the organizer:</p>
              <div class="details">
                <p><strong>Meeting:</strong> {$subject}</p>
                <p><strong>Start Time:</strong> {$start_time}</p>
                <p><strong>End Time:</strong> {$end_time}</p>
                <p><strong>Organizer:</strong> {$organizer}</p>
              </div>
              <div class="footer">
                <p>This is an automated email from the system.</p>
                <p>Please do not reply to this message.</p>
              </div>
            </div>
          </body>
        </html>
        HTML;
    }

    public function buildMeetingBody(array $details): string
    {
        // Normalize rooms to array
        $roomIds = is_array($details['rooms']) ? $details['rooms'] : explode(',', $details['rooms']);

        $roomAreaList = $this->getData($roomIds);
        $organizer = $this->getOrganizer($details['created_by']);
        $emailHeader = $details['id'] ? "Meeting Updated" : "New Meeting Scheduled";
        $headline = $details['id'] ? "updated" : "scheduled";

        $roomDisplay = '';
        foreach ($roomAreaList as $roomArea) {
            $roomDisplay .= htmlspecialchars($roomArea['area_name']) . ' - ' . htmlspecialchars($roomArea['room_name']) . '<br>';
        }

        if (empty($roomDisplay)) {
            $roomDisplay = 'No rooms assigned';
        }
        
        $allDayText    = !empty($details['all_day']) ? 'Yes' : 'No';
        $subject     = htmlspecialchars($details['name']);
        $description = nl2br(htmlspecialchars($details['description']));
        $type        = htmlspecialchars($details['type']);
        $start_time = $this->timeDateString($details['start_time']);
        $end_time = $this->timeDateString($details['end_time']);
        $duration = $details['end_time'] - $details['start_time'];
        $duration_hours = floor($duration / 3600);
        $duration_minutes = floor(($duration % 3600) / 60);
        $duration_string = sprintf("%d hours %d minutes", $duration_hours, $duration_minutes);
        $confirmed = htmlspecialchars($details['confirmed']);
        $representative = htmlspecialchars($details['representative']);
        $representativeHtml = '';
        
        if ($type === 'External') {
            $representativeHtml = "<p><strong>Representative:</strong> {$representative}</p>";
        }
        
        return <<<HTML
        <html>
          <head>
            <style>
              body { font-family: Arial, sans-serif; color: #333; }
              .container { padding: 20px; border: 1px solid #e0e0e0; background-color: #f9f9f9; max-width: 600px; margin: auto; }
              h2 { color: #2c3e50; }
              .details { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin-top: 15px; }
              .footer { font-size: 12px; color: #888; margin-top: 20px; }
            </style>
          </head>
          <body>
            <div class="container">
              <h2>{$emailHeader}</h2>
              <p>Good day</p>
              <p>The following meeting has been <strong>{$headline}</strong> by the organizer:</p>
              <div class="details">
                <p><strong>Subject:</strong> {$subject}</p>
                <p><strong>Description:</strong> {$description}</p>
                <p><strong>Organizer:</strong> {$organizer}</p>
                <p><strong>Start Time:</strong> {$start_time}</p>
                <p><strong>End Time:</strong> {$end_time}</p>
                <p><strong>Duration:</strong> {$duration_string}</p>
                <p><strong>All-Day Event:</strong> {$allDayText}</p>
                <p><strong>Type:</strong> {$type}</p>
                <p><strong>Area / Rooms:</strong><br>{$roomDisplay}</p>
                <p><strong>Confirmation status: </strong>{$confirmed}</p>
                {$representativeHtml}
              </div>
              <div class="footer">
                <p>This is an automated email from the system.</p>
                <p>Please do not reply to this message.</p>
              </div>
            </div>
          </body>
        </html>
        HTML;
    }

    public function getData($roomIds): array
    {
        if (!is_array($roomIds)) {
            $roomIds = explode(',', $roomIds);
        }

        $roomIds = array_filter($roomIds, fn($id) => is_numeric($id) && $id > 0);

        if (empty($roomIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($roomIds) - 1) . '?';
        $sql = "SELECT r.room_name, a.area_name
                FROM mrbs_room AS r
                JOIN mrbs_area AS a ON r.area_id = a.id
                WHERE r.id IN ($placeholders)
                ORDER BY a.area_name, r.room_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($roomIds);
        return $stmt->fetchAll();
    }

    public function getOrganizer($id): string
    {
        $sql = "SELECT display_name FROM mrbs_users WHERE name LIKE ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id . '%']);
        $result = $stmt->fetch();
        return $result ? $result['display_name'] : $id;
    }

    public function saveRepresentative($id, $email, $existingId): void
    {
        // Delete existing
        $deleteSql = "DELETE FROM mrbs_groups WHERE entry_id = ?";
        $stmt = $this->db->prepare($deleteSql);
        $stmt->execute([$existingId]);

        // Insert new
        $insertSql = "INSERT INTO mrbs_groups (entry_id, email) VALUES (?, ?)";
        $stmt = $this->db->prepare($insertSql);
        $stmt->execute([$id, $email]);
    }

    private function timeDateString($timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}