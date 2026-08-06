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

rem Which version is actually inside the file we just wrote? Printed below so
rem there is never any doubt about what is being sent to a phone.
set "APPVER=unknown"
for /f "tokens=2" %%v in ('findstr /b "version:" C:\dev\aun-app\pubspec.yaml') do set "APPVER=%%v"

rem Delete APKs left by earlier versions of this script under the OLD name.
rem This caused a real, expensive bug: a stale AUN-Projector-app.apk from an
rem earlier build sat next to the fresh one for weeks, and the old file was the
rem one that got installed — so a fix that was verified, built and shipped
rem looked like it had simply never worked. Two similar filenames in one folder
rem is a trap; the build owns this folder, so it cleans up after itself.
if exist "%~dp0AUN-Projector-app.apk" (
    del /q "%~dp0AUN-Projector-app.apk"
    echo  Removed an old AUN-Projector-app.apk from a previous build.
)

echo.
echo ==========================================================
echo  DONE!  The APK is saved next to this script as:
echo.
echo     AUN-Care-Bangladesh.apk
echo.
echo  Version: %APPVER%
echo  Signed with: %SIGNING%
echo.
echo  Send THIS file to your phone (WhatsApp/USB), tap to install.
echo  If any other .apk is sitting in this folder, it is out of date.
echo ==========================================================
echo.
echo  NOTE: the first build after switching to the upload key
echo  will NOT install over an older debug-signed copy. Uninstall
echo  the old app on each phone once, then install this one.
echo ==========================================================
echo.
pause
