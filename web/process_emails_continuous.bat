@echo off
cd /d "D:\wwwroot\mrbs\web"
echo MRBS Email Processor started   > processor.log

:loop
"D:\PHP\php.exe" "background_email.php" >> email_output.log
timeout /t 5 /nobreak >nul
goto loop
