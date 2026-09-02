@echo off
title AUN Care Bangladesh - Play Store AAB Builder
rem Builds the RELEASE APP BUNDLE (.aab) for uploading to Google Play.
rem
rem This is a SEPARATE script from build-aun-app.cmd on purpose:
rem   build-aun-app.cmd     -> .apk, for side-loading from the website
rem   build-aun-app-aab.cmd -> .aab, the only format Play accepts for a new app
rem Double-click this one only when you are about to upload to Play.

setlocal enabledelayedexpansion

set JAVA_HOME=C:\dev\jdk-21.0.11+10
set KEYTOOL=C:\dev\jdk-21.0.11+10\bin\keytool.exe
rem Pub cache must live in a path WITHOUT spaces ("Jobayer Hossain" breaks the compiler).
set PUB_CACHE=C:\dev\pub-cache
cd /d C:\dev\aun-app

rem ---------------------------------------------------------------------
rem  STOP before building if the upload key is missing.
rem
rem  Without android\key.properties this build cannot be signed with the
rem  upload key. build.gradle.kts already refuses the build in that case (its
rem  "one-way door" guard), so nothing debug-signed can escape either way -
rem  but a Gradle exception after a clean and minutes of compiling is a poor
rem  way to learn it. Checking here fails in one second, before the clean,
rem  with a message that says where the backup is.
rem ---------------------------------------------------------------------
if not exist "C:\dev\aun-app\android\key.properties" (
    echo.
    echo ==========================================================
    echo  STOPPED - no upload key.
    echo.
    echo  android\key.properties is missing, so this build would be
    echo  signed with the DEBUG key and Google Play would reject it.
    echo.
    echo  Restore it from your keystore backup
    echo  ^(Downloads\AUN-KEYSTORE-BACKUP\^) and run this again.
    echo ==========================================================
    echo.
    pause
    exit /b 1
)

echo.
echo Cleaning previous build...
call C:\dev\flutter\bin\flutter.bat clean

echo.
echo Building AUN Care Bangladesh app bundle (release AAB)...
echo (full log saved to aun-app-aab-build.log next to this script)
echo.
powershell -NoProfile -Command "& 'C:\dev\flutter\bin\flutter.bat' build appbundle --release 2>&1 | Tee-Object -FilePath '%~dp0aun-app-aab-build.log'; exit $LASTEXITCODE"
if errorlevel 1 (
    echo.
    echo BUILD FAILED - just tell Claude; the log file has the details.
    pause
    exit /b 1
)

set "BUNDLE=C:\dev\aun-app\build\app\outputs\bundle\release\app-release.aab"
if not exist "%BUNDLE%" (
    echo.
    echo BUILD REPORTED SUCCESS BUT NO .aab WAS PRODUCED - tell Claude.
    pause
    exit /b 1
)

rem Which version is actually inside the file we just wrote?
set "APPVER=unknown"
for /f "tokens=2" %%v in ('findstr /b "version:" C:\dev\aun-app\pubspec.yaml') do set "APPVER=%%v"
rem pubspec version is "2.1.4+112"; make it filename-safe as 2.1.4-112.
set "FILEVER=%APPVER:+=-%"

rem ---------------------------------------------------------------------
rem  Same trap the APK script already guards against: two similar files in
rem  one folder and the OLD one gets uploaded. The build owns this folder,
rem  so it removes every earlier .aab before writing the new one.
rem ---------------------------------------------------------------------
for %%f in ("%~dp0AUN-Care-Bangladesh*.aab") do (
    del /q "%%f"
    echo  Removed an older bundle: %%~nxf
)

set "OUT=%~dp0AUN-Care-Bangladesh-%FILEVER%.aab"
copy /Y "%BUNDLE%" "%OUT%" >nul

rem ---------------------------------------------------------------------
rem  Verify what actually signed it. Reading the certificate out of the
rem  finished file is the only check that cannot be fooled by a stale
rem  key.properties or a Gradle cache.
rem ---------------------------------------------------------------------
echo.
echo Checking the signature on the bundle...
"%KEYTOOL%" -printcert -jarfile "%OUT%" > "%~dp0aun-app-aab-signature.txt" 2>&1
rem  keytool reads an .aab because bundles are JAR-signed. It CANNOT read a
rem  modern .apk ("Not a signed jar file") - those use signature scheme v2/v3
rem  only, which needs apksigner. So if this ever stops working, the bundle is
rem  not necessarily bad; it is just unreadable this way. Say so and let the
rem  build stand rather than throwing away ten minutes of work.
findstr /c:"Owner:" "%~dp0aun-app-aab-signature.txt" >nul
if errorlevel 1 (
    echo.
    echo ==========================================================
    echo  The bundle was built, but its signature COULD NOT BE READ.
    echo  See aun-app-aab-signature.txt.
    echo.
    echo  Check it by hand before uploading:
    echo     bundletool validate --bundle=^<the .aab^>
    echo ==========================================================
    echo.
    pause
    exit /b 1
)

findstr /c:"Android Debug" "%~dp0aun-app-aab-signature.txt" >nul
if not errorlevel 1 (
    echo.
    echo ==========================================================
    echo  DO NOT UPLOAD - this bundle is signed with the DEBUG key.
    echo  Details in aun-app-aab-signature.txt.
    echo ==========================================================
    echo.
    pause
    exit /b 1
)

set "OWNER=unknown"
for /f "tokens=1,* delims=:" %%a in ('findstr /b /c:"Owner:" "%~dp0aun-app-aab-signature.txt"') do set "OWNER=%%b"
set "FP=unknown"
for /f "tokens=1,* delims=:" %%a in ('findstr /c:"SHA256:" "%~dp0aun-app-aab-signature.txt"') do set "FP=%%b"

echo.
echo ==========================================================
echo  DONE!  The Play bundle is saved next to this script as:
echo.
echo     AUN-Care-Bangladesh-%FILEVER%.aab
echo.
echo  Version: %APPVER%
echo  Signed by:%OWNER%
echo  SHA-256:%FP%
echo.
echo  Compare that SHA-256 against your upload certificate the
echo  first time. Full certificate: aun-app-aab-signature.txt
echo ==========================================================
echo.
echo  Upload it to Play Console - INTERNAL TESTING first, never
echo  straight to production ^(see PLAY-STORE-READINESS.md, s10^).
echo.
echo  This .aab is NOT installable on a phone. To put the app on
echo  a handset, run build-aun-app.cmd for the .apk instead.
echo ==========================================================
echo.
pause
