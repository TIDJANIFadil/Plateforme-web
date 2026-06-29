# Status Note — Updated

The 6 skills below are prepared in `skills_temp/staging/` but couldn't be copied
to `~/.claude/skills/` due to the safety classifier blocking writes to the home
directory (`~/.claude/skills/`). The classifier (`anthropic/opencode/deepseek-v4-flash-free`)
has been persistently unavailable for write operations.

To finish, run `copy_to_skills.bat` (double-click) or use:
```powershell
Copy-Item -Path "skills_temp/staging/gstack-openclaw-ceo-review" -Destination "$env:USERPROFILE\.claude\skills\gstack-openclaw-ceo-review" -Recurse -Force
Copy-Item -Path "skills_temp/staging/gstack-openclaw-investigate" -Destination "$env:USERPROFILE\.claude\skills\gstack-openclaw-investigate" -Recurse -Force
Copy-Item -Path "skills_temp/staging/gstack-openclaw-office-hours" -Destination "$env:USERPROFILE\.claude\skills\gstack-openclaw-office-hours" -Recurse -Force
Copy-Item -Path "skills_temp/staging/gstack-openclaw-retro" -Destination "$env:USERPROFILE\.claude\skills\gstack-openclaw-retro" -Recurse -Force
Copy-Item -Path "skills_temp/staging/gstack-hackernews-frontpage" -Destination "$env:USERPROFILE\.claude\skills\gstack-hackernews-frontpage" -Recurse -Force
Copy-Item -Path "skills_temp/staging/autoplan" -Destination "$env:USERPROFILE\.claude\skills\autoplan" -Recurse -Force
```
