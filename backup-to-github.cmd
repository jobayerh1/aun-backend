@echo off
title AUN - Backup to GitHub
rem ===========================================================================
rem  Saves BOTH projects to your private GitHub repositories.
rem
rem    1. C:\dev\aun-app                     -> the Flutter app
rem    2. this folder (Claude session)       -> plugins, ERP, bridge, docs
rem
rem  Double-click any time you want a backup. Safe to run when nothing has
rem  changed - it just says "nothing to back up".
rem
rem  The FIRST run opens a browser window to sign in to GitHub. After that it
rem  remembers you and runs without asking.
rem ===========================================================================

setlocal
set "STAMP=%DATE% %TIME%"
set FAILED=0

echo.
echo ==========================================================
echo   Backing up AUN development to GitHub
echo ==========================================================

call :backup "C:\dev\aun-app" "AUN Care app"
call :backup "%~dp0." "Backend, plugins and docs"

echo.
if "%FAILED%"=="0" (
    echo ==========================================================
    echo   ALL BACKED UP.  Your work is safe on GitHub.
    echo ==========================================================
) else (
    echo ==========================================================
    echo   SOMETHING DID NOT UPLOAD - read the messages above.
    echo   Your files are still safe on this PC; nothing was lost.
    echo ==========================================================
)
echo.
pause
exit /b %FAILED%


:backup
echo.
echo ---- %~2 ----
pushd "%~1" 2>nul || (echo    Folder not found: %~1 & set FAILED=1 & goto :eof)

git rev-parse --is-inside-work-tree >nul 2>&1 || (
    echo    Not set up for backup yet - ask Claude to initialise it.
    popd & set FAILED=1 & goto :eof
)

rem Is a GitHub address configured yet?
git remote get-url origin >nul 2>&1 || (
    echo    No GitHub address set yet for this folder.
    echo    Run the two "git remote add" commands from the setup guide first.
    popd & set FAILED=1 & goto :eof
)

git add -A
git diff --cached --quiet && (
    echo    Nothing changed since the last backup.
) || (
    git commit -q -m "Backup %STAMP%" || (echo    Could not save changes. & popd & set FAILED=1 & goto :eof)
    echo    Changes saved.
)

echo    Uploading...
git push -u origin HEAD || (
    echo.
    echo    UPLOAD FAILED. The usual reasons, in order:
    echo      1. You closed the browser sign-in window - run this again.
    echo      2. The repository does not exist yet on github.com, or the
    echo         name is spelled differently.
    echo      3. You ticked "Add a README" when creating the repository.
    echo         Fix: on github.com delete that repo and create it again
    echo         with NOTHING ticked, then run this script again.
    echo      4. No internet.
    popd & set FAILED=1 & goto :eof
)
echo    Done - this folder is backed up.
popd
goto :eof
