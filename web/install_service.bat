@echo off
echo ========================================
echo    MRBS Email Processor Complete Setup
echo ========================================
echo.
echo Installing continuous email processor with automated log management...
cd /d "D:\wwwroot\mrbs\web"

:: Remove existing tasks
echo Removing existing tasks...
schtasks /delete /tn "MRBS Email Processor AutoStart" /f 2>nul
schtasks /delete /tn "MRBS Email Processor Monitor" /f 2>nul
schtasks /delete /tn "MRBS Log Cleanup" /f 2>nul
schtasks /delete /tn "MRBS Log Rotation" /f 2>nul

:: Step 1: Create the continuous email processor
echo.
echo Step 1: Creating email processor...
(
  echo @echo off
  echo cd /d "D:\wwwroot\mrbs\web"
  echo echo MRBS Email Processor started ^%date^% ^%time^% ^> processor.log
  echo.
  echo :loop
  echo "D:\PHP\php.exe" "background_email.php" ^>^> email_output.log
  echo timeout /t 5 /nobreak ^>nul
  echo goto loop
) > process_emails_continuous.bat

:: Test PHP installation
echo Testing PHP installation...
"D:\PHP\php.exe" -v
if errorlevel 1 (
    echo ERROR: PHP not found at D:\PHP\php.exe
    echo Please check PHP installation.
    pause
    exit /b 1
)

:: Step 2: Start email processor immediately
echo.
echo Step 2: Starting email processor...
start "MRBS Email Processor" /MIN process_emails_continuous.bat

:: Step 3: Create auto-start task for email processor
echo.
echo Step 3: Creating auto-start task...
schtasks /create /tn "MRBS Email Processor AutoStart" ^
  /tr "D:\wwwroot\mrbs\web\process_emails_continuous.bat" ^
  /sc onstart ^
  /ru "SYSTEM" ^
  /rl HIGHEST ^
  /f

:: Step 4: Create monitoring task (restarts if crashed)
echo.
echo Step 4: Creating crash monitor...
schtasks /create /tn "MRBS Email Processor Monitor" ^
  /tr "cmd /c 'tasklist | findstr \"php.exe\" >nul || start \"MRBS Email Processor\" /MIN \"D:\wwwroot\mrbs\web\process_emails_continuous.bat\"'" ^
  /sc hourly ^
  /ru "SYSTEM" ^
  /f

:: Step 5: Create daily log rotation
echo.
echo Step 5: Setting up daily log rotation...
schtasks /create /tn "MRBS Log Rotation" ^
  /tr "cmd /c 'cd /d \"D:\wwwroot\mrbs\web\" && if exist email_output.log move email_output.log email_output_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log && echo Log rotated ^%date^% ^%time^% ^>^> processor.log'" ^
  /sc daily ^
  /st 23:59 ^
  /ru "SYSTEM" ^
  /f

:: Step 6: Create automated log cleanup (30 days retention)
echo.
echo Step 6: Setting up log cleanup...
schtasks /create /tn "MRBS Log Cleanup" ^
  /tr "cmd /c 'forfiles /p \"D:\wwwroot\mrbs\web\" /m \"email_output_*.log\" /d -30 /c \"cmd /c del @path\" && echo Logs cleaned ^%date^% ^%time^% ^>^> processor.log'" ^
  /sc weekly ^
  /d SUN ^
  /st 02:00 ^
  /ru "SYSTEM" ^
  /f

:: Step 7: Verify everything is running
echo.
echo Step 7: Verifying installation...
timeout /t 3 /nobreak >nul

echo Checking email processor...
tasklist | findstr "php.exe" >nul
if errorlevel 1 (
    echo ⚠️  PHP not running yet - may take a moment to start
) else (
    echo ✅ Email processor is running
)

echo Checking scheduled tasks...
schtasks /query /tn "MRBS Email Processor AutoStart" >nul && echo ✅ Auto-start task installed
schtasks /query /tn "MRBS Email Processor Monitor" >nul && echo ✅ Crash monitor installed
schtasks /query /tn "MRBS Log Rotation" >nul && echo ✅ Log rotation installed
schtasks /query /tn "MRBS Log Cleanup" >nul && echo ✅ Log cleanup installed

:: Final summary
echo.
echo ========================================
echo         SETUP COMPLETE!
echo ========================================
echo.
echo ✅ Email Processor: Running every 5 seconds
echo ✅ Auto-Start: Will run after server reboots
echo ✅ Crash Protection: Auto-restarts if stopped
echo ✅ Log Rotation: Daily at 11:59 PM
echo ✅ Log Cleanup: Weekly, keeps 30 days only
echo.
echo 📍 Log Files Location: D:\wwwroot\mrbs\web\
echo 📍 Processor Log: processor.log
echo 📍 Email Log: email_output.log ^(rotated daily^)
echo.
echo To monitor: tasklist ^| findstr "php.exe"
echo To view logs: type "D:\wwwroot\mrbs\web\email_output.log"
echo.
pause