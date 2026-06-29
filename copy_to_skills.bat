@echo off
REM ────────────────────────────────────────────────────────────
REM copy_to_skills.bat
REM Copy 6 remaining skills from staging to ~/.claude/skills/
REM Double-click to run
REM ────────────────────────────────────────────────────────────

set SKILLS=%USERPROFILE%\.claude\skills
set SRC=%~dp0skills_temp\staging

echo Copying remaining skills to %SKILLS%
echo.

for %%d in (gstack-openclaw-ceo-review gstack-openclaw-investigate gstack-openclaw-office-hours gstack-openclaw-retro gstack-hackernews-frontpage autoplan) do (
    if exist "%SRC%\%%d\SKILL.md" (
        if not exist "%SKILLS%\%%d" mkdir "%SKILLS%\%%d"
        copy /y "%SRC%\%%d\SKILL.md" "%SKILLS%\%%d\SKILL.md" >nul
        echo   [OK] %%d
    ) else (
        echo   [--] %%d (SKILL.md not found)
    )
)

echo.
echo Done! 6 skills processed.
pause
