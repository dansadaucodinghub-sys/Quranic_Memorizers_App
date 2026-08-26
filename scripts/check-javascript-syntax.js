import { spawnSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

function files(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);
        return entry.isDirectory() ? files(path) : (entry.isFile() && path.endsWith('.js') ? [path] : []);
    });
}

const targets = [...files('public/assets/js'), ...files('tests/Frontend'), ...files('scripts')];
for (const target of targets) {
    const result = spawnSync(process.execPath, ['--check', target], { stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status ?? 1);
}
console.log(`JavaScript syntax valid: ${targets.length} files.`);
