<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Vite build asset helper.
 *
 * Resolves the stylesheet files that belong to a Vite entry, including CSS
 * emitted for shared chunks. The PHP views link these files explicitly because
 * the application loads the compiled JavaScript bundle directly instead of
 * rendering Vite's generated HTML entry.
 */

if (!function_exists('vite_manifest')) {
    function vite_manifest(): array
    {
        static $manifest = null;
        if ($manifest !== null) {
            return $manifest;
        }

        $path = dirname(__DIR__, 2) . '/public/build/.vite/manifest.json';
        if (!is_file($path)) {
            return $manifest = [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        return $manifest = is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('vite_entry_css')) {
    function vite_entry_css(string $entry): array
    {
        $manifest = vite_manifest();
        if ($manifest === []) {
            return [];
        }

        $seen = [];
        $css = [];
        $collect = function (string $key) use (&$collect, &$seen, &$css, $manifest): void {
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $chunk = $manifest[$key] ?? null;
            if (!is_array($chunk)) {
                return;
            }

            foreach (($chunk['css'] ?? []) as $file) {
                if (is_string($file) && $file !== '' && !in_array($file, $css, true)) {
                    $css[] = $file;
                }
            }

            foreach (($chunk['imports'] ?? []) as $import) {
                if (is_string($import) && $import !== '') {
                    $collect($import);
                }
            }
        };

        $collect($entry);
        return $css;
    }
}
