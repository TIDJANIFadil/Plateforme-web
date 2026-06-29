# Install Skills — Claude Code Skills Installer

Ce dossier contient les scripts pour installer les skills Claude Code depuis les plugins locaux.

## Fichiers

| Fichier | Description |
|---------|-------------|
| `install_skills.ps1` | Script PowerShell principal (installer les skills) |
| `RUN_ME_AFTER_INSTALL.bat` | Script batch Windows (alternative) |
| `setup_skills.sh` | Script Bash (alternative) |
| `MISSING.md` | Rapport des skills non trouvés |

## Installation rapide

### Option 1: PowerShell (recommandé)
```powershell
powershell -ExecutionPolicy Bypass -File c:\xampp\htdocs\Plateforme-web\install_skills.ps1
```

### Option 2: Batch Windows
Double-clique sur `RUN_ME_AFTER_INSTALL.bat` dans l'explorateur.

### Option 3: Bash (Git Bash)
```bash
bash c:/xampp/htdocs/Plateforme-web/setup_skills.sh
```

## Que font les scripts

1. **GStack skills** (52 skills) → `~/.claude/skills/gstack-*/`  
2. **Superpowers** (14 skills) → `~/.claude/skills/`  
3. **Claude-Mem** (16 skills) → `~/.claude/skills/mem-*/`  

Au total : **~82 skills** installés depuis les plugins locaux.
