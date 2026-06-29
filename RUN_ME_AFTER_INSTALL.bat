@echo off
REM =============================================================
REM Install all Claude Code skills from local plugin sources
REM Run this from: c:\xampp\htdocs\Plateforme-web\
REM =============================================================
setlocal enabledelayedexpansion

set SKILLS_DIR=%USERPROFILE%\.claude\skills
set PLUGINS_DIR=%USERPROFILE%\.claude\plugins

if not exist "%SKILLS_DIR%" mkdir "%SKILLS_DIR%"
echo Skills directory: %SKILLS_DIR%
echo.

:: ---- 1. GStack Skills ----
echo [1/3] Installing GStack skills...
set GSKILLS=autoplan benchmark benchmark-models browse canary careful codex context-restore context-save cso design-consultation design-html design-review design-shotgun devex-review document-generate document-release freeze gstack-upgrade guard health investigate ios-clean ios-design-review ios-fix ios-qa ios-sync land-and-deploy landing-report learn make-pdf office-hours open-gstack-browser pair-agent plan-ceo-review plan-design-review plan-devex-review plan-eng-review plan-tune qa qa-only retro review scrape setup-browser-cookies setup-deploy setup-gbrain ship skillify spec sync-gbrain unfreeze

for %%s in (%GSKILLS%) do (
    if exist "%PLUGINS_DIR%\gstack\%%s\SKILL.md" (
        if not exist "%SKILLS_DIR%\gstack-%%s" (
            xcopy /E /I /Q "%PLUGINS_DIR%\gstack\%%s" "%SKILLS_DIR%\gstack-%%s" >nul
            echo   OK: gstack-%%s
        ) else (
            echo   EXISTS: gstack-%%s
        )
    ) else (
        echo   SKIP: gstack-%%s (no SKILL.md)
    )
)

:: Connect-chrome alias
if exist "%SKILLS_DIR%\gstack-open-gstack-browser\SKILL.md" (
    if not exist "%SKILLS_DIR%\gstack-connect-chrome" (
        xcopy /E /I /Q "%SKILLS_DIR%\gstack-open-gstack-browser" "%SKILLS_DIR%\gstack-connect-chrome" >nul
        echo   OK: gstack-connect-chrome (alias)
    )
)

:: OpenClaw sub-skills
if exist "%PLUGINS_DIR%\gstack\openclaw\skills" (
    for /d %%s in ("%PLUGINS_DIR%\gstack\openclaw\skills\*") do (
        set SKNAME=%%~ns
        if exist "%%s\SKILL.md" (
            if not exist "%%s\SKILL.md" (
                xcopy /E /I /Q "%%s" "%SKILLS_DIR%\!SKNAME!" >nul
                echo   OK: !SKNAME! (openclaw)
            )
        )
    )
)

:: ---- 2. Superpowers Skills ----
echo [2/3] Installing Superpowers skills...
if exist "%PLUGINS_DIR%\superpowers\skills" (
    for /d %%s in ("%PLUGINS_DIR%\superpowers\skills\*") do (
        set SKNAME=%%~ns
        if exist "%%s\SKILL.md" (
            if not exist "!SKNAME!" (
                xcopy /E /I /Q "%%s" "%SKILLS_DIR%\!SKNAME!" >nul
                echo   OK: !SKNAME!
            )
        )
    )
)

:: ---- 3. Claude-Mem Skills ----
echo [3/3] Installing Claude-Mem skills...
if exist "%PLUGINS_DIR%\claude-mem\plugin\skills" (
    for /d %%s in ("%PLUGINS_DIR%\claude-mem\plugin\skills\*") do (
        set SKNAME=%%~ns
        if exist "%%s\SKILL.md" (
            if not exist "mem-!SKNAME!" (
                xcopy /E /I /Q "%%s" "%SKILLS_DIR%\mem-!SKNAME!" >nul
                echo   OK: mem-!SKNAME!
            )
        )
    )
)

:: ---- Count ----
echo.
echo ==========================================
set COUNT=0
for /d %%s in ("%SKILLS_DIR%\*") do (
    if exist "%%s\SKILL.md" set /a COUNT+=1
)
echo Total skills installed: %COUNT%
echo ==========================================

pause
