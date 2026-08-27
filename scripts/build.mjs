import { createHash } from 'node:crypto';
import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';

let projectRoot = new URL('../', import.meta.url);
let assetsDirectory = new URL('_public/assets/', projectRoot);
let licensesDirectory = new URL('licenses/', assetsDirectory);
let sourcesDirectory = new URL('sources/', assetsDirectory);

await mkdir(licensesDirectory, { recursive: true });
await mkdir(sourcesDirectory, { recursive: true });

let chartPackage = JSON.parse(await readFile(new URL('node_modules/chart.js/package.json', projectRoot), 'utf8'));
let chessPackage = JSON.parse(await readFile(new URL('node_modules/chess.js/package.json', projectRoot), 'utf8'));
let stockfishPackage = JSON.parse(await readFile(new URL('node_modules/stockfish/package.json', projectRoot), 'utf8'));
let stockfishBaseName = `stockfish-${stockfishPackage.buildVersion}-lite-single`;
let files = [
    ['node_modules/chart.js/dist/chart.umd.min.js', '_public/assets/chart.umd.min.js'],
    ['node_modules/chess.js/dist/esm/chess.js', '_public/assets/chess.js'],
    [`node_modules/stockfish/bin/${stockfishBaseName}.js`, '_public/assets/stockfish-18-lite-single.js'],
    [`node_modules/stockfish/bin/${stockfishBaseName}.wasm`, '_public/assets/stockfish-18-lite-single.wasm'],
    ['node_modules/chart.js/LICENSE.md', '_public/assets/licenses/chart.js-LICENSE.md'],
    ['node_modules/chess.js/LICENSE', '_public/assets/licenses/chess.js-LICENSE.txt'],
    ['node_modules/stockfish/Copying.txt', '_public/assets/licenses/stockfish-COPYING.txt']
];

await Promise.all(
    files.map(([source, target]) => copyFile(new URL(source, projectRoot), new URL(target, projectRoot)))
);

let stockfishMetadataResponse = await fetch(`https://registry.npmjs.org/stockfish/${stockfishPackage.version}`);
if (!stockfishMetadataResponse.ok) {
    throw new Error(`Stockfish-Metadaten konnten nicht geladen werden (HTTP ${stockfishMetadataResponse.status}).`);
}
let stockfishMetadata = await stockfishMetadataResponse.json();
if (typeof stockfishMetadata.gitHead !== 'string' || !/^[a-f0-9]{40}$/.test(stockfishMetadata.gitHead)) {
    throw new Error('Der Stockfish-Quellcode-Commit fehlt in den npm-Metadaten.');
}

let stockfishSourceUrl = `https://github.com/nmrugg/stockfish.js/archive/${stockfishMetadata.gitHead}.tar.gz`;
let stockfishSourceResponse = await fetch(stockfishSourceUrl);
if (!stockfishSourceResponse.ok) {
    throw new Error(`Stockfish-Quellcode konnte nicht geladen werden (HTTP ${stockfishSourceResponse.status}).`);
}
await writeFile(
    new URL('stockfish.js-source.tar.gz', sourcesDirectory),
    Buffer.from(await stockfishSourceResponse.arrayBuffer())
);

let checksumFiles = [
    'chart.umd.min.js',
    'chess.js',
    'stockfish-18-lite-single.js',
    'stockfish-18-lite-single.wasm',
    'chess-pieces.svg'
];
let checksums = await Promise.all(
    checksumFiles.map(async filename => {
        let contents = await readFile(new URL(filename, assetsDirectory));
        return `${createHash('sha256').update(contents).digest('hex')}  ${filename}`;
    })
);
let stockfishSource = await readFile(new URL('stockfish.js-source.tar.gz', sourcesDirectory));
checksums.push(`${createHash('sha256').update(stockfishSource).digest('hex')}  stockfish.js-source.tar.gz`);

let sources = `# Third-party build sources

The production build contains these unmodified browser distributions:

- Chart.js ${chartPackage.version}: https://www.npmjs.com/package/chart.js/v/${chartPackage.version} (MIT)
- chess.js ${chessPackage.version}: https://www.npmjs.com/package/chess.js/v/${chessPackage.version} (BSD-2-Clause)
- Stockfish.js ${stockfishPackage.version}: https://www.npmjs.com/package/stockfish/v/${stockfishPackage.version} (GPL-3.0)
- Wikimedia/Cburnett chess pieces: https://github.com/shaack/cm-chessboard/blob/master/assets/pieces/standard.svg (CC BY-SA 3.0: https://creativecommons.org/licenses/by-sa/3.0/)

The complete corresponding Stockfish.js source is published as \`/assets/sources/stockfish.js-source.tar.gz\` and comes from:

- ${stockfishSourceUrl}

SHA-256 checksums:

\`\`\`text
${checksums.join('\n')}
\`\`\`
`;
await writeFile(new URL('SOURCES.md', licensesDirectory), sources);
