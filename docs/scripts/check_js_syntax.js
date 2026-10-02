const fs = require('fs');
const vm = require('vm');
const path = 'resources/views/fileindexing/js/javascript.blade.php';
let code = fs.readFileSync(path, 'utf8');
const start = code.indexOf('<script>');
const end = code.lastIndexOf('</script>');
code = (start >= 0 && end > start) ? code.slice(start + 8, end) : code;
code = code.replace(/{{[^}]*}}/g, '""');
const lines = code.split('\n');
let stack = 0;
let inSingle = false;
let inDouble = false;
let inTemplate = false;
let escape = false;
for (let lineIndex = 0; lineIndex < lines.length; lineIndex++) {
  const line = lines[lineIndex];
  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    if (escape) {
      escape = false;
      continue;
    }
    if (char === '\\') {
      escape = true;
      continue;
    }
    if (inSingle) {
      if (char === "'") inSingle = false;
      continue;
    }
    if (inDouble) {
      if (char === '"') inDouble = false;
      continue;
    }
    if (inTemplate) {
      if (char === '`') inTemplate = false;
      continue;
    }
    if (char === "'") { inSingle = true; continue; }
    if (char === '"') { inDouble = true; continue; }
    if (char === '`') { inTemplate = true; continue; }
    if (char === '(') {
      stack++;
    } else if (char === ')') {
      stack--;
      if (stack < 0) {
        console.error('Extra closing parenthesis at line', lineIndex + 1);
        console.error(line);
        process.exit(0);
      }
    }
  }
}
if (stack > 0) {
  console.error('Missing', stack, 'closing parenthesis');
}
