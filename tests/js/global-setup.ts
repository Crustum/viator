import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));

export default function setup(): void {
    execFileSync('php', [path.join(dir, 'generate.php')], { stdio: 'inherit' });
}
