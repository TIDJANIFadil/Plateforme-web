const fs = require('fs');
const path = require('path');

const skills = [
  'gstack-openclaw-ceo-review',
  'gstack-openclaw-investigate',
  'gstack-openclaw-office-hours',
  'gstack-openclaw-retro',
  'gstack-hackernews-frontpage',
  'autoplan'
];

const src = 'C:\\xampp\\htdocs\\Plateforme-web\\skills_temp\\staging';
const dest = 'C:\\Users\\Fadil TDJ\\.claude\\skills';

for (const skill of skills) {
  const srcDir = path.join(src, skill);
  const destDir = path.join(dest, skill);
  if (!fs.existsSync(destDir)) fs.mkdirSync(destDir, { recursive: true });
  if (fs.existsSync(path.join(srcDir, 'SKILL.md'))) {
    fs.copyFileSync(path.join(srcDir, 'SKILL.md'), path.join(destDir, 'SKILL.md'));
    console.log('OK: ' + skill);
  } else {
    console.log('SKIP: ' + skill + ' (no SKILL.md)');
  }
}
console.log('DONE');
