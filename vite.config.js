import { defineConfig, loadEnv } from 'vite';
import fs from 'node:fs';
import path from 'node:path';

const rootPath = process.cwd();
const hotFile = path.resolve(rootPath, 'storage/vite.hot');

function phpHotFile() {
    const cleanup = () => {
        try {
            if (fs.existsSync(hotFile)) {
                fs.unlinkSync(hotFile);
                console.log(`PHP hot file removed: ${hotFile}`);
            }
        } catch {
            // Safely ignore errors if already unlinked
        }
    };

    return {
        name: 'php-hot-file',

        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const address = server.resolvedUrls?.local?.[0];

                if (!address) {
                    throw new Error(
                        'Could not determine Vite dev server URL.'
                    );
                }

                fs.mkdirSync(path.dirname(hotFile), { recursive: true });
                fs.writeFileSync(hotFile, address);

                console.log(`PHP hot file created: ${hotFile}`);
                console.log(`Vite URL: ${address}`);
            });

            server.httpServer?.once('close', cleanup);

            process.once('SIGINT', cleanup);
            process.once('SIGTERM', cleanup);
            process.once('SIGHUP', cleanup);
            process.once('exit', cleanup);
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, rootPath, '');

    return {
        base: env.VITE_BASE || '/',

        plugins: [phpHotFile()],

        server: {
            port: 5173,
        },

        build: {
            manifest: true,
            outDir: 'dist',
            emptyOutDir: true,

            rollupOptions: {
                input: {
                    main: path.resolve(rootPath, 'src/main.js'),
                },
            },
        },
    };
});
