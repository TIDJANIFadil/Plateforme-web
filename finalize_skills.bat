@echo off
REM ──────────────────────────────────────────────────────────────
REM finalize_skills.bat — Complete skill installer
REM Handles: copy 6 remaining skills + install official plugins
REM ──────────────────────────────────────────────────────────────
setlocal enabledelayedexpansion
set SKILLS=%USERPROFILE%\.claude\skills
set SRC=%~dp0skills_temp\staging

title Finalizing Claude Code Skills Installation

echo === Step 1/2: Copy 6 remaining skills ===
echo.

for %%d in (gstack-openclaw-ceo-review gstack-openclaw-investigate gstack-openclaw-office-hours gstack-openclaw-retro gstack-hackernews-frontpage autoplan) do (
    if exist "%SRC%\%%d\SKILL.md" (
        if not exist "%SKILLS%\%%d" mkdir "%SKILLS%\%%d"
        copy /y "%SRC%\%%d\SKILL.md" "%SKILLS%\%%d\SKILL.md" >nul
        echo   [OK] %%d
    ) else (
        echo   [SKIP] %%d (not found in staging)
    )
)

echo.
echo === Step 2/2: Install official Anthropic plugins ===
echo This step requires internet ^& your Claude API key.
echo.

where claude >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo Installing frontend-design...
    call claude plugin install frontend-design
    echo Installing code-review...
    call claude plugin install code-review
    echo Installing security-guidance...
    call claude plugin install security-guidance
) else (
    echo [WARN] claude CLI not found in PATH
    echo To install manually:
    echo   claude plugin install frontend-design
    echo   claude plugin install code-review
    echo   claude plugin install security-guidance
)

echo.
echo === Counting installed skills ===
set COUNT=0
for /d %%d in ("%SKILLS%\*") do (
    if exist "%%d\SKILL.md" set /a COUNT+=1
)
echo Total skills in %SKILLS%: %COUNT%

echo.
echo === DONE ===
echo If you want to search for more skills:
echo   claude plugin search [skill-name]
echo   clawhub search [keywords]
echo.
pause
