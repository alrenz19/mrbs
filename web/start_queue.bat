@echo off
title MRBS Email Queue Processor
cd /d "C:\inetpub\wwwroot\mrbs\web"
echo Starting MRBS Email Queue Processor...
"D:\PHP\php.exe" queue_processor.php start
echo Queue processor stopped.
pause