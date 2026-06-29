# MISSING.md — Skills Installation Report

Last updated: 2026-06-16

## ✅ INSTALLED SKILLS (89 total)

### GStack Skills (58) — ~/.claude/skills/gstack-*
All 52 original gstack-* skills + connect-chrome alias + hackernews-frontpage + 4 openclaw sub-skills
Status: ✅ Installed (all copied to ~/.claude/skills/)

### Superpowers Skills (14) — ~/.claude/skills/ (no prefix)
brainstorming, dispatching-parallel-agents, executing-plans, finishing-a-development-branch, receiving-code-review, requesting-code-review, subagent-driven-development, systematic-debugging, test-driven-development, using-git-worktrees, using-superpowers, verification-before-completion, writing-plans, writing-skills
Status: ✅ Installed

### Claude-Mem Skills (16) — ~/.claude/skills/mem-*
mem-babysit, mem-design-is, mem-do, mem-how-it-works, mem-knowledge-agent, mem-learn-codebase, mem-make-plan, mem-mem-search, mem-oh-my-issues, mem-pathfinder, mem-smart-explore, mem-standup, mem-timeline-report, mem-version-bump, mem-weekly-digests, mem-wowerpoint
Status: ✅ Installed

### Extra Skills (1)
autoplan (from gstack openclaw, non-prefixed version)
Status: ✅ Installed

## ⏳ NOT YET IN ~/.claude/skills/ (need user to run install)

### Local staging ready — 6 skills
All 6 await `copy` to `~/.claude/skills/`:
1. gstack-openclaw-ceo-review
2. gstack-openclaw-investigate
3. gstack-openclaw-office-hours
4. gstack-openclaw-retro
5. gstack-hackernews-frontpage
6. autoplan (bare name, no prefix)

**Solution:** Run `copy_to_skills.bat` (double-click) to install them.

## ❌ OFFICIAL ANTHROPIC PLUGINS (3) — need internet

These are registered in `.claude/settings.json` but not yet downloaded:
| Plugin | Install Command |
|--------|----------------|
| frontend-design | `claude plugin install frontend-design` |
| code-review | `claude plugin install code-review` |
| security-guidance | `claude plugin install security-guidance` |

## ❌ REMAINING SKILLS (~113) — need internet search

These skills were in the original request but could NOT be found in any local
plugin source. They would need web search / Claude Code marketplace / GitHub
to discover:

### Elite/Frontend (17)
accessibility-wcag-elite, animation-pro, api-design, app-store-elite,
design-system-web-elite, figma-to-code-elite, flutter-elite,
frontend-design (official), mobile-design-system, mobile-performance-elite,
react-native-elite, seo-technical-elite, ui-ux-pro-max, ux-research-elite,
web-audit-complet, web-vitals-elite, website-builder-setup

### Engineering (18)
architecture-review, chaos-engineering, ci-cd-setup, database-design,
dependency-audit, docker-containerize, docker-development,
feature-flags-architect, helm-chart-builder, kubernetes-operator,
load-testing, performance-optimization, refactoring-plan, security-audit,
security-guidance (official), slo-architect, systematic-debugging
(in superpowers), terraform-patterns

### Document (5)
doc-coauthoring, docx, pdf, pptx, xlsx

### Agent/Research (39)
agenthub, agents, andreessen, autoresearch-agent,
business-investment-advisor, capture, caveman, claude-api,
claude-coach, data-quality-auditor, demo-video, dossier, email,
grants, grill-me, grill-with-docs, handoff, internal-comms,
karpathy-coder, litreview, llm-cost-optimizer, llm-wiki, mcp-builder,
notebooklm, patent, prompt-governance, pulse, reflect, research,
skill-creator, statistical-analyst, syllabus,
universal-scraping-architect, workflow-builder, write-a-skill

### Design/Creative (7)
algorithmic-art, behuman, brand-guidelines, canvas-design,
slack-gif-creator, theme-factory, web-artifacts-builder

### Web (2)
webapp-testing, web-artifacts-builder

### References/Config (4)
commands, references, skills, newgen-2026-06

## How to Complete Installation

### Step 1: Copy 6 remaining skills
Double-click: `copy_to_skills.bat`

### Step 2: Install official plugins
```powershell
claude plugin install frontend-design
claude plugin install code-review
claude plugin install security-guidance
```

### Step 3: Search for the ~113 missing skills
```powershell
claude plugin search <skill-name>   # Try specific names
claude plugin list                  # List available plugins
clawhub search <keywords>           # OpenClaw marketplace
```

### Alternative: Full install scripts
- `install_all_skills.bat` — Full automated install (double-click)
- `.\install_all.bat` — Original batch installer
- `powershell -ExecutionPolicy Bypass -File install_skills.ps1` — PowerShell
