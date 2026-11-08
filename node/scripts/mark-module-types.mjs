// Each build folder declares its module type, so Node and TypeScript read
// dist/esm as ES modules and dist/cjs as CommonJS whatever the root says.
import { writeFileSync } from 'node:fs';

writeFileSync(new URL('../dist/esm/package.json', import.meta.url), '{"type":"module"}\n');
writeFileSync(new URL('../dist/cjs/package.json', import.meta.url), '{"type":"commonjs"}\n');
