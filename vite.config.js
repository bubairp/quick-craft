import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

const origin = `${process.env.DDEV_PRIMARY_URL ?? 'https://quick-craft.ddev.site'}:5173`;

// Full browser reload when a Twig template changes (Tailwind classes are rescanned automatically)
const twigReload = () => ({
  name: 'twig-reload',
  configureServer(server) {
    server.watcher.add('templates/**/*.twig');
    server.watcher.on('change', (file) => {
      if (file.endsWith('.twig')) server.ws.send({ type: 'full-reload', path: '*' });
    });
  },
});

export default defineConfig(({ command }) => ({
  // Dev: assets served from the dev server root. Build: served from /dist/
  base: command === 'serve' ? '' : '/dist/',
  plugins: [tailwindcss(), twigReload()],
  publicDir: false,
  build: {
    manifest: true,
    outDir: 'web/dist',
    emptyOutDir: true,
    rolldownOptions: {
      input: { main: 'src/main.js' },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin,
    allowedHosts: ['.ddev.site'],
    cors: { origin: /https?:\/\/([A-Za-z0-9\-.]+)?\.ddev\.site(?::\d+)?$/ },
    watch: { ignored: ['**/vendor/**', '**/storage/**', '**/web/dist/**'] },
  },
}));
