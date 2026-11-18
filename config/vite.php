<?php
/**
 * craft-vite settings — see vendor/nystudio107/craft-vite/src/config.php for all options.
 *
 * Dev: run `ddev npm run dev` → assets are served from the Vite dev server on :5173 (HMR).
 * If the dev server is not running, checkDevServer makes Craft fall back to the
 * built files in web/dist (run `ddev npm run build`).
 */

use craft\helpers\App;

$siteUrl = rtrim((string) App::env('PRIMARY_SITE_URL'), '/');

return [
    'useDevServer' => App::env('CRAFT_ENVIRONMENT') === 'dev',
    'checkDevServer' => true,
    // Browser-facing URL of the dev server (DDEV exposes container port 5173 over HTTPS)
    'devServerPublic' => $siteUrl . ':5173/',
    // URL PHP uses inside the web container to ping the dev server
    'devServerInternal' => 'http://localhost:5173/',
    'serverPublic' => $siteUrl . '/dist/',
    'manifestPath' => '@webroot/dist/.vite/manifest.json',
    'errorEntry' => 'src/main.js',
];
