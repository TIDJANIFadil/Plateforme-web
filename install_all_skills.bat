@echo off
REM ────────────────────────────────────────────────────────────
REM install_all_skills.bat
REM Complete installer for all 89 Claude Code skills
REM Double-click this file or run from Command Prompt
REM ────────────────────────────────────────────────────────────

setlocal enabledelayedexpansion
set SKILLS=%USERPROFILE%\.claude\skills
set STAGING=%~dp0skills_temp\staging

echo === Installing all Claude Code skills ===
echo Target: %SKILLS%
echo.

if not exist "%STAGING%" (
    echo ERROR: staging directory not found at %STAGING%
    echo Run the copy stage first.
    pause
    exit /b 1
)

mkdir "%SKILLS%" 2>nul

set COUNT=0

REM ─── Copy all skills from staging ─────────────────────────
for /d %%d in ("%STAGING%\*") do (
    if exist "%%d\SKILL.md" (
        xcopy /e /i /y /q "%%d" "%SKILLS%\%%~nxd\" >nul
        set /a COUNT+=1
        echo   [OK] %%~nxd
    )
)

REM ─── Install official Anthropic plugins ───────────────────
echo.
echo === Installing official Anthropic plugins ===
call npx claude plugin install frontend-design 2>nul
call npx claude plugin install code-review 2>nul
call npx claude plugin install security-guidance 2>nul

REM ─── Summary ───────────────────────────────────────────────
echo.
echo ========================================
echo INSTALLATION COMPLETE!
echo Total skills installed: %COUNT%
echo Directory: %SKILLS%
echo ========================================
echo.
echo To install official plugins manually:
echo   claude plugin install frontend-design
echo   claude plugin install code-review
echo   claude plugin install security-guidance
echo.
echo Need more skills? Search for missing ones:
echo   claude plugin search [skill-name]
echo   clawhub search [keywords]
echo.
pause
