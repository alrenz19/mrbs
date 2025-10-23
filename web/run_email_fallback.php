<?php
// run_email_fallback.php
sleep(10); // Wait 10 seconds for background process

if (isset($argv[1]) && file_exists($argv[1])) {
    // If data file still exists, background process failed
    require 'background_email.php';
    MRBS\BackgroundEmail::processEmailJob($argv[1]);
}
?>