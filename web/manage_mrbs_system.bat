@echo off
echo MRBS Email Processor Management
echo ===============================
echo.
echo 1 - Stop Email Processor
echo 2 - Start Email Processor
echo 3 - Check Status
echo 4 - View Recent Logs
echo 5 - Force Log Rotation
echo 6 - Force Log Cleanup
echo.
set /p choice="Choose option (1-6): "

if "%choice%"=="1" (
    echo Stopping email processor...
    taskkill /f /im php.exe /t >nul 2>&1
    taskkill /f /im cmd.exe /t >nul 2>&1
    echo ✅ Stopped
) else if "%choice%"=="2" (
    echo Starting email processor...
    cd /d "D:\wwwroot\mrbs\web"
    start "MRBS Email Processor" /MIN process_emails_continuous.bat
    echo ✅ Started
) else if "%choice%"=="3" (
    echo Checking status...
    tasklist | findstr "php.exe" >nul && echo ✅ Running || echo ❌ Stopped
    echo.
    echo Scheduled Tasks:
    schtasks /query /tn "MRBS Email Processor AutoStart" | find "Ready"
    schtasks /query /tn "MRBS Email Processor Monitor" | find "Ready"
    schtasks /query /tn "MRBS Log Rotation" | find "Ready"
    schtasks /query /tn "MRBS Log Cleanup" | find "Ready"
) else if "%choice%"=="4" (
    echo Recent log entries:
    echo ===================
    cd /d "D:\wwwroot\mrbs\web"
    if exist email_output.log (
        tail -10 email_output.log
    ) else (
        echo No email_output.log found
    }
) else if "%choice%"=="5" (
    echo Rotating logs...
    cd /d "D:\wwwroot\mrbs\web"
    if exist email_output.log (
        move email_output.log email_output_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%.log
        echo ✅ Log rotated
    ) else (
        echo No log file to rotate
    }
) else if "%choice%"=="6" (
    echo Cleaning old logs...
    forfiles /p "D:\wwwroot\mrbs\web" /m "email_output_*.log" /d -30 /c "cmd /c del @path"
    echo ✅ Old logs deleted
) else (
    echo Invalid choice
}

echo.
pause