const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const fg = require('fast-glob');

// Path to theme root (relative to this script).
const themeDir = path.join(__dirname, '.');

// Path to theme's libraries.yml file.
const librariesFile = path.join(themeDir, 'nicsdru_dept_theme.libraries.yml'); // Adjust if needed.

// Asset path patterns of files to hash, including subdirectories.
const assetPatterns = [
  'css/**/*.css',
  'js/**/*.js',
  'images/**/*.*'
];

async function generateHash() {
  let files = await fg(assetPatterns, { cwd: themeDir, absolute: true });

  files.sort(); // Ensure deterministic order.

  const hash = crypto.createHash('md5');

  for (const file of files) {
    let fileBuffer = fs.readFileSync(file);
    let normalizedContent = fileBuffer.toString().replace(/\r\n/g, '\n'); // Normalize line endings.
    hash.update(normalizedContent);
  }

  return hash.digest('hex').substring(0, 10);
}

async function updateLibrariesYml() {
  const newVersion = await generateHash();

  let content = fs.readFileSync(librariesFile, 'utf8');
  content = content.replace(/version: .*/, `version: '${newVersion}'`);

  fs.writeFileSync(librariesFile, content, 'utf8');
  console.log(`✅ Updated theme version to ${newVersion} in libraries.yml`);
}

updateLibrariesYml().catch(console.error);
