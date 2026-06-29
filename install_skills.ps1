# ──────────────────────────────────────────────────────────────
# Install Skills — gstack + superpowers + claude-mem
# ──────────────────────────────────────────────────────────────

$ErrorActionPreference = "Continue"
$SKILLS_DIR = "$env:USERPROFILE\.claude\skills"
$PLUGINS_DIR = "$env:USERPROFILE\.claude\plugins"

if (-not (Test-Path $SKILLS_DIR)) { New-Item -ItemType Directory -Path $SKILLS_DIR -Force | Out-Null }

Write-Host "=== Installing all available skills ===`n" -ForegroundColor Cyan

# ─── Helper: copy a skill directory ──────────────────────────
$INSTALLED = @()

function Install-SkillDir {
    param([string]$SourceDir, [string]$SkillName, [string]$TargetName = $SkillName)

    $target = "$SKILLS_DIR\$TargetName"
    $source = "$SourceDir\$SkillName"

    if (-not (Test-Path "$source\SKILL.md")) {
        Write-Host "  SKIP: $TargetName — no SKILL.md" -ForegroundColor Yellow
        return $false
    }

    if (Test-Path $target) {
        Remove-Item -Recurse -Force $target -ErrorAction SilentlyContinue | Out-Null
    }

    try {
        Copy-Item -Recurse -Path $source -Destination $target -Force -ErrorAction Stop
        Write-Host "  OK: $TargetName" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "  FAIL: $TargetName — $_" -ForegroundColor Red
        return $false
    }
}

# ==============================================================
# 1. GStack Skills
# ==============================================================
Write-Host "`n--- 1. GStack skills ---" -ForegroundColor Yellow

$gstackBase = "$PLUGINS_DIR\gstack"
Get-ChildItem "$gstackBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
    $name = $_.Name
    if ($name -in @("openclaw", "browser-skills")) {
        Write-Host "  skip nested: $name" -ForegroundColor Gray
        return
    }
    $targetName = "gstack-$name"
    if (Install-SkillDir -SourceDir $gstackBase -SkillName $name -TargetName $targetName) {
        $script:INSTALLED += $targetName
    }
}

# OpenClaw sub-skills
$openclawBase = "$gstackBase\openclaw\skills"
if (Test-Path $openclawBase) {
    Get-ChildItem "$openclawBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
        $name = $_.Name
        if (Install-SkillDir -SourceDir $openclawBase -SkillName $name) {
            $script:INSTALLED += $name
        }
    }
}

# browser-skills sub-skills
$browserBase = "$gstackBase\browser-skills"
if (Test-Path $browserBase) {
    Get-ChildItem "$browserBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
        $name = "gstack-$($_.Name)"
        if (Install-SkillDir -SourceDir $browserBase -SkillName $_.Name -TargetName $name) {
            $script:INSTALLED += $name
        }
    }
}

# connect-chrome alias
if (Test-Path "$SKILLS_DIR\gstack-open-gstack-browser\SKILL.md") {
    $target = "$SKILLS_DIR\gstack-connect-chrome"
    if (-not (Test-Path "$target\SKILL.md")) {
        Copy-Item -Recurse -Path "$SKILLS_DIR\gstack-open-gstack-browser" -Destination $target -Force
        Write-Host "  OK: gstack-connect-chrome (alias)" -ForegroundColor Green
        $script:INSTALLED += "gstack-connect-chrome"
    }
}

# ==============================================================
# 2. Superpowers Skills
# ==============================================================
Write-Host "`n--- 2. Superpowers skills ---" -ForegroundColor Yellow

$superBase = "$PLUGINS_DIR\superpowers\skills"
if (Test-Path $superBase) {
    Get-ChildItem "$superBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
        $name = $_.Name
        if (Install-SkillDir -SourceDir $superBase -SkillName $name) {
            $script:INSTALLED += $name
        }
    }
}

# ==============================================================
# 3. Claude-Mem Skills
# ==============================================================
Write-Host "`n--- 3. Claude-Mem skills ---" -ForegroundColor Yellow

$memPluginBase = "$PLUGINS_DIR\claude-mem\plugin\skills"
if (Test-Path $memPluginBase) {
    Get-ChildItem "$memPluginBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
        $name = "mem-$($_.Name)"
        if (Install-SkillDir -SourceDir $memPluginBase -SkillName $_.Name -TargetName $name) {
            $script:INSTALLED += $name
        }
    }
}

$memOpenclawBase = "$PLUGINS_DIR\claude-mem\openclaw\skills"
if (Test-Path $memOpenclawBase) {
    Get-ChildItem "$memOpenclawBase\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" } | ForEach-Object {
        $name = "mem-$($_.Name)"
        if (Install-SkillDir -SourceDir $memOpenclawBase -SkillName $_.Name -TargetName $name) {
            $script:INSTALLED += $name
        }
    }
}

# ==============================================================
# 4. Summary
# ==============================================================
Write-Host "`n=== Summary ===" -ForegroundColor Cyan

# Count all skills with SKILL.md
$total = (Get-ChildItem "$SKILLS_DIR\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" }).Count
Write-Host "Total skills with SKILL.md in $SKILLS_DIR : $total" -ForegroundColor Green
Write-Host "Just installed: $($INSTALLED.Count)" -ForegroundColor Green

# List installed
Write-Host "`nInstalled skills:" -ForegroundColor Cyan
$INSTALLED | Sort-Object | ForEach-Object { Write-Host "  - $_" }

Write-Host "`nDone!" -ForegroundColor Green
