<?php
namespace MRBS;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'lib/vendor/autoload.php';

// Load .env once when this file is loaded
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

class Email
{
    private PHPMailer $mail;

    public function __construct()
    {
        // Initialize PHPMailer once
        $this->mail = new PHPMailer(true);

        // Configure SMTP here (called once per Email instance)
        $this->mail->isSMTP();
        // $this->mail->Host       = 'smtp.office365.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
        $this->mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // More readable constant
        $this->mail->Port       = 587;
        $this->mail->setFrom($_ENV['SMTP_USERNAME'], 'Meeting Reminder');
        $this->mail->isHTML(true);
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
        $start_time = time_date_string($details['start_time']);
        $end_time = time_date_string($details['end_time']);
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
        $start_time = time_date_string($details['start_time']);
        $end_time = time_date_string($details['end_time']);
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

    /**
     * Get rooms and areas info for given room IDs
     * @param array|string $roomIds array or comma-separated string of room IDs
     * @return array list of rooms with area info
     */

    public function getData($roomIds): array
    {
        if (!is_array($roomIds)) {
            $roomIds = explode(',', $roomIds);
        }

        $roomIds = array_filter($roomIds, fn($id) => is_numeric($id) && $id > 0);

        if (empty($roomIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        $sql = "SELECT r.room_name, a.area_name
                FROM " . _tbl('room') . " AS r
                JOIN " . _tbl('area') . " AS a ON r.area_id = a.id
                WHERE r.id IN ($placeholders)
                ORDER BY a.area_name, r.room_name";

        $result = db()->sql_query($sql, $roomIds);
        return  $result;
    }

    public function getOrganizer($id): string
    {
        $sql = "SELECT display_name FROM " . _tbl('users') . " WHERE name LIKE ?";
        return db()->string_query($sql, $id . '%');
    }

    public function saveRepresentative($id, $email, $existingId): void
    {
        $columns = ['entry_id', 'email'];
        $placeholders = ['?', '?'];
        $values = [$id, $email];

        $sql = "INSERT INTO " . _tbl('groups') .
              " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        db()->query($sql, $values);

        $sqlDel = "DELETE FROM " . _tbl('groups') .
          " WHERE entry_id = ?";
        db()->query($sqlDel, array($existingId));
    }
}
