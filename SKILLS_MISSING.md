# Skills Manquantes (MISSING.md)

Généré le : 2026-06-16

## Résumé

Les skills suivants n'ont **pas** pu être installés car ils ne font pas partie des sources locales disponibles (plugins installés). Ils nécessiteraient une recherche dans le marketplace Claude Code, des dépôts GitHub communautaires, ou l'installation via la CLI `claude plugin install`.

| Catégorie | Installables (locaux) | Demandés | Manquants |
|-----------|----------------------|----------|-----------|
| GStack | 52 | 55 | 0 *(local)* |
| Superpowers | 14 | 14 | 0 *(local)* |
| Claude-Mem | 16 | 16 | 0 *(local)* |
| Officiels Anthropic | 0* | 3 | 3 |
| Elite/Frontend/Mobile | 0 | 17 | 17 |
| Engineering | 0 | 18 | 18 |
| Workflow | 0 | 15 | 15 |
| Document | 0 | 5 | 5 |
| Agent/Research | 0 | 39 | 39 |
| Design/Creative | 0 | 7 | 7 |
| Web | 0 | 2 | 2 |
| Références/Config | 0 | 4 | 4 |
| **Total** | **82** | **~195** | **~113** |

\* Les 3 skills officiels (frontend-design, code-review, security-guidance) sont déjà enregistrés dans settings.json mais les fichiers SKILL.md sont des stubs de ~400 octets chacun — ils pointent vers le marketplace Anthropic.

---

## Skills Éligibles via `claude plugin install` (officiels Anthropic)

```
claude plugin install frontend-design
claude plugin install code-review
claude plugin install security-guidance
```

Ces 3 sont déjà dans `.claude/settings.json` mais les SKILL.md locaux sont des stubs.

---

## Skills à rechercher (sources potentielles)

### GitHub — Communauté Claude Code

Ces skills pourraient exister dans des repos GitHub communautaires :

| Skill | Source possible |
|-------|----------------|
| api-design | Repos communautaires Claude Code |
| database-design | Repos/Fournisseurs communautaires |
| performance-optimization | Repos/Fournisseurs communautaires |
| security-audit | Repos/Fournisseurs communautaires |
| architecture-review | Repos/Fournisseurs communautaires |
| chaos-engineering | Repos/Fournisseurs communautaires |
| ci-cd-setup | Repos/Fournisseurs communautaires |
| docker-containerize | Repos/Fournisseurs communautaires |
| docker-development | Repos/Fournisseurs communautaires |
| load-testing | Repos/Fournisseurs communautaires |
| terraform-patterns | Repos/Fournisseurs communautaires |
| kubernetes-operator | Repos/Fournisseurs communautaires |
| helm-chart-builder | Repos/Fournisseurs communautaires |
| refactoring-plan | Repos/Fournisseurs communautaires |

### GitHub — Design/Creative

| Skill | Source possible |
|-------|----------------|
| algorithmic-art | GitHub communautaire |
| brand-guidelines | GitHub communautaire |
| canvas-design | GitHub communautaire |
| theme-factory | GitHub communautaire |
| web-artifacts-builder | GitHub communautaire |
| slack-gif-creator | GitHub communautaire |
| behuman | GitHub communautaire |

### GitHub — Document/Agent

| Skill | Source possible |
|-------|----------------|
| mcp-builder | GitHub MCP communautaire |
| agility | GitHub communautaire |
| email | GitHub communautaire |
| research | GitHub communautaire |
| docx, pdf, pptx, xlsx | GitHub/Packages document |
| litreview, statistical-analyst | GitHub communautaire |
| notification, patent | GitHub communautaire |
| claude-api, agenthub, agents | GitHub communautaire |

---

## Conclusion

**82 skills** sont installables immédiatement depuis les 3 plugins locaux (GStack, Superpowers, Claude-Mem).

Les **~113 skills restants** nécessiteraient soit :
1. Une recherche manuelle sur GitHub (`recherche: "claude code skill"`)
2. Une souscription au marketplace Claude Code
3. Leur création/rédaction manuelle (chaque skill = un fichier SKILL.md)
4. L'installation via la CLI `claude plugin install` pour les officiels Anthropic

Pour lancer l'installation des 82 skills disponibles :
```bash
bash c:/xampp/htdocs/Plateforme-web/setup_skills.sh
```
