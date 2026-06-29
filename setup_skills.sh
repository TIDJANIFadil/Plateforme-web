#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
# setup_skills.sh — Install all Claude Code skills from local plugins
# ──────────────────────────────────────────────────────────────
# Run: bash c:/xampp/htdocs/Plateforme-web/setup_skills.sh
# ──────────────────────────────────────────────────────────────

set -e

SKILLS_DIR="$HOME/.claude/skills"
PLUGINS_DIR="$HOME/.claude/plugins"

echo "=== Installing all available skills ==="
echo "Target: $SKILLS_DIR"
mkdir -p "$SKILLS_DIR"

count=0

# ─── 1. GStack skills ────────────────────────────────────────
echo ""
echo "--- GStack skills (52) ---"
GSTACK_BASE="$PLUGINS_DIR/gstack"

SKILL_LIST="autoplan benchmark benchmark-models browse canary careful codex context-restore context-save cso design-consultation design-html design-review design-shotgun devex-review document-generate document-release freeze gstack-upgrade guard health investigate ios-clean ios-design-review ios-fix ios-qa ios-sync land-and-deploy landing-report learn make-pdf office-hours open-gstack-browser pair-agent plan-ceo-review plan-design-review plan-devex-review plan-eng-review plan-tune qa qa-only retro review scrape setup-browser-cookies setup-deploy setup-gbrain ship skillify spec sync-gbrain unfreeze"

for dir in $SKILL_LIST; do
    if [ -f "$GSTACK_BASE/$dir/SKILL.md" ]; then
        cp -r "$GSTACK_BASE/$dir" "$SKILLS_DIR/gstack-$dir"
        echo "  OK: gstack-$dir"
        count=$((count + 1))
    else
        echo "  SKIP: gstack-$dir (no SKILL.md)"
    fi
done

# connect-chrome alias
if [ -d "$SKILLS_DIR/gstack-open-gstack-browser" ]; then
    cp -r "$SKILLS_DIR/gstack-open-gstack-browser" "$SKILLS_DIR/gstack-connect-chrome"
    echo "  OK: gstack-connect-chrome (alias)"
    count=$((count + 1))
fi

# OpenClaw sub-skills
OPENCLAW_DIR="$GSTACK_BASE/openclaw/skills"
if [ -d "$OPENCLAW_DIR" ]; then
    for skill_dir in "$OPENCLAW_DIR"/*/; do
        if [ -f "$skill_dir/SKILL.md" ]; then
            name=$(basename "$skill_dir")
            cp -r "$skill_dir" "$SKILLS_DIR/$name"
            echo "  OK: $name (openclaw)"
            count=$((count + 1))
        fi
    done
fi

# ─── 2. Superpowers skills ───────────────────────────────────
echo ""
echo "--- Superpowers skills (14) ---"
SUPER_BASE="$PLUGINS_DIR/superpowers/skills"
if [ -d "$SUPER_BASE" ]; then
    for skill_dir in "$SUPER_BASE"/*/; do
        if [ -f "$skill_dir/SKILL.md" ]; then
            name=$(basename "$skill_dir")
            cp -r "$skill_dir" "$SKILLS_DIR/$name"
            echo "  OK: $name"
            count=$((count + 1))
        fi
    done
fi

# ─── 3. Claude-Mem skills ────────────────────────────────────
echo ""
echo "--- Claude-Mem skills ---"
MEM_BASE="$PLUGINS_DIR/claude-mem/plugin/skills"
if [ -d "$MEM_BASE" ]; then
    for skill_dir in "$MEM_BASE"/*/; do
        if [ -f "$skill_dir/SKILL.md" ]; then
            name=$(basename "$skill_dir")
            cp -r "$skill_dir" "$SKILLS_DIR/mem-$name"
            echo "  OK: mem-$name"
            count=$((count + 1))
        fi
    done
fi

# ─── Summary ──────────────────────────────────────────────────
echo ""
echo "========================================"
echo "INSTALLATION COMPLETE!"
echo "Total skills installed: $count"
echo "Directory: $SKILLS_DIR"
echo "========================================"
echo ""
echo "Next steps for missing skills:"
echo "  claude plugin install frontend-design"
echo "  claude plugin install code-review"
echo "  claude plugin install security-guidance"
echo "See SKILLS_MISSING.md for the full gap analysis."
