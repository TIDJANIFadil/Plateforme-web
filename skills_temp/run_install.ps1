# ────────────────────────────────────────────────────────────
# Install all skills to ~/.claude/skills/
# ────────────────────────────────────────────────────────────
$ErrorActionPreference = "Stop"
$PROJECT = "c:\xampp\htdocs\Plateforme-web"
$SKILLS = "$env:USERPROFILE\.claude\skills"
$PLUGINS = "$env:USERPROFILE\.claude\plugins"

# Create target directory
New-Item -ItemType Directory -Path $SKILLS -Force -ErrorAction SilentlyContinue | Out-Null

# Install GStack skills (prefixed)
$gstackDirs = Get-ChildItem "$PLUGINS\gstack\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" }
$skipDirs = @("openclaw", "browser-skills")
$count = 0

foreach ($d in $gstackDirs) {
    $name = $d.Name
    if ($name -in $skipDirs) { continue }
    $target = "$SKILLS\gstack-$name"
    Copy-Item -Recurse -Path $d.FullName -Destination $target -Force
    $count++
    Write-Host "  gstack-$name"
}

# Install Superpowers skills
$superDir = "$PLUGINS\superpowers\skills"
if (Test-Path $superDir) {
    foreach ($d in (Get-ChildItem "$superDir\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" })) {
        $target = "$SKILLS\$($d.Name)"
        Copy-Item -Recurse -Path $d.FullName -Destination $target -Force
        $count++
        Write-Host "  $($d.Name)"
    }
}

# Install Claude-Mem skills (prefixed mem-)
$memDir = "$PLUGINS\claude-mem\plugin\skills"
if (Test-Path $memDir) {
    foreach ($d in (Get-ChildItem "$memDir\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" })) {
        $target = "$SKILLS\mem-$($d.Name)"
        Copy-Item -Recurse -Path $d.FullName -Destination $target -Force
        $count++
        Write-Host "  mem-$($d.Name)"
    }
}

# Install Claude-Mem openclaw skills
$memOcDir = "$PLUGINS\claude-mem\openclaw\skills"
if (Test-Path $memOcDir) {
    foreach ($d in (Get-ChildItem "$memOcDir\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" })) {
        $target = "$SKILLS\mem-$($d.Name)"
        Copy-Item -Recurse -Path $d.FullName -Destination $target -Force
        $count++
        Write-Host "  mem-$($d.Name)"
    }
}

# Final count
$total = (Get-ChildItem "$SKILLS\*" -Directory | Where-Object { Test-Path "$_\SKILL.md" }).Count
Write-Host "`n=== DONE: $count copied, $total total in $SKILLS ==="
