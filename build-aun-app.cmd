@echo off
title AUN Care Bangladesh - APK Builder
rem Builds the release APK of the AUN Care Bangladesh customer app.
rem Double-click this file. First run takes a few minutes.

set JAVA_HOME=C:\dev\jdk-21.0.11+10
rem Pub cache must live in a path WITHOUT spaces ("Jobayer Hossain" breaks the compiler).
set PUB_CACHE=C:\dev\pub-cache
cd /d C:\dev\aun-app

rem Which key will sign this build? android\key.properties holds the real upload
rem key; without it Gradle falls back to the debug key, which must never ship.
if exist "C:\dev\aun-app\android\key.properties" (
    set "SIGNING=RELEASE (your upload key)"
) else (
    set "SIGNING=DEBUG KEY - test builds only, do NOT publish"
)

echo.
echo Cleaning previous build (needed after the scanner build-config fix)...
call C:\dev\flutter\bin\flutter.bat clean

echo.
echo Building AUN Care Bangladesh app (release APK)...
echo (full log saved to aun-app-build.log next to this script)
echo.
powershell -NoProfile -Command "& 'C:\dev\flutter\bin\flutter.bat' build apk --release 2>&1 | Tee-Object -FilePath '%~dp0aun-app-build.log'; exit $LASTEXITCODE"
if errorlevel 1 (
    echo.
    echo BUILD FAILED - just tell Claude; the log file has the details.
    pause
    exit /b 1
)

copy /Y "C:\dev\aun-app\build\app\outputs\flutter-apk\app-release.apk" "%~dp0AUN-Care-Bangladesh.apk" >nul

echo.
echo ==========================================================
echo  DONE!  The APK is saved next to this script as:
echo.
echo     AUN-Care-Bangladesh.apk
echo.
echo  Signed with: %SIGNING%
echo.
echo  Send it to your phone (WhatsApp/USB), tap it to install.
echo ==========================================================
echo.
echo  NOTE: the first build after switching to the upload key
echo  will NOT install over an older debug-signed copy. Uninstall
echo  the old app on each phone once, then install this one.
echo ==========================================================
echo.
pause
