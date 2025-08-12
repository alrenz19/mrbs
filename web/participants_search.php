<?php
declare(strict_types=1);

namespace MRBS;

require 'defaultincludes.inc';
require_once 'mrbs_sql.inc';
require_once 'functions_ical.inc';
require 'lib/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

// Get and sanitize the query string
$query = trim($_POST['query'] ?? '');

if (strlen($query) < 2) {
    exit;
}

$sql = "
    SELECT display_name, email
    FROM " . _tbl('users') . "
    WHERE display_name LIKE ? AND email != ''
    ORDER BY display_name ASC
    LIMIT 10";

$params = ["%$query%"];

// Use the sql_query() method for direct associative array results
$rows = db()->sql_query($sql, $params);

// Output results as HTML <div> suggestions
if (!empty($rows)) {
    foreach ($rows as $row) {
        $display_name = htmlspecialchars($row['display_name']);
        $email = !empty($row['email']) ? htmlspecialchars($row['email']) : '';
        echo "<div class='suggestion-item' data-name='{$display_name}' data-email='{$email}' style='padding: 6px 12px; cursor: pointer; border: 1px solid #ccc'>{$display_name}</div>";
    }
}
