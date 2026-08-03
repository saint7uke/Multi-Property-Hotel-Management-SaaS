import { cp, mkdir, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const publicDirectory = path.join(root, 'public');
const outputDirectory = path.join(root, '.vercel-static');

const directories = ['build', 'css', 'fonts', 'images', 'js'];
const files = ['MALogo.png', 'favicon.ico', 'robots.txt'];

await rm(outputDirectory, { recursive: true, force: true });
await mkdir(outputDirectory, { recursive: true });

for (const directory of directories) {
    const source = path.join(publicDirectory, directory);

    if (existsSync(source)) {
        await cp(source, path.join(outputDirectory, directory), { recursive: true });
    }
}

for (const file of files) {
    const source = path.join(publicDirectory, file);

    if (existsSync(source)) {
        await cp(source, path.join(outputDirectory, file));
    }
}

console.log('Prepared Vercel static output without PHP entry points.');
