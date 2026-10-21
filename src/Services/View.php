<?php
/**
 * src/Services/View.php
 * Lightweight Native PHP View Renderer & Layout Manager.
 */

declare(strict_types=1);

namespace Vault\Services;

use RuntimeException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class View
{
    private static string $viewsDir = __DIR__ . '/../Views/';

    /**
     * Renders a view file, optionally nested inside a master layout.
     *
     * @param string $view Relative view name (e.g., 'dashboard/index')
     * @param array<string, mixed> $data Variables exposed to the view
     * @param string|null $layout Master layout file (e.g., 'layout') or null for partials
     * @return string Rendered HTML content
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $viewFile = self::resolveViewPath($view);

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View template not found: {$view}");
        }

        // Render the inner view template within an isolated scope
        $content = self::evaluateTemplate($viewFile, $data);

        // If no layout is specified, return the rendered partial directly
        if ($layout === null) {
            return $content;
        }

        $layoutFile = self::resolveViewPath($layout);
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout template not found: {$layout}");
        }

        // Pass inner view output to the layout via $content variable
        $layoutData = array_merge($data, ['content' => $content]);

        return self::evaluateTemplate($layoutFile, $layoutData);
    }

    /**
     * Escapes raw text for safe HTML output (XSS mitigation).
     */
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Shorthand alias for View::escape().
     */
    public static function e(?string $value): string
    {
        return self::escape($value);
    }

    /**
     * Appends a cache-busting timestamp query parameter (?v=...) to an image asset URL
     * based on the parent record's modification date/timestamp.
     *
     * @param string|null $path Relative or absolute web path to asset
     * @param string|int|null $modified Datetime string or UNIX timestamp of parent record
     * @return string Formatted asset URL with ?v=<timestamp>
     */
    public static function assetUrl(?string $path, mixed $modified = null): string
    {
        if ($path === null) {
            return '';
        }
        $trimmed = trim($path);
        if ($trimmed === '' || str_starts_with($trimmed, 'data:')) {
            return $trimmed;
        }

        // Normalize URL with leading slash if relative
        $url = (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '/'))
            ? $trimmed
            : '/' . ltrim($trimmed, '/\\');

        // Parse base URL and existing query params if any
        $parts = explode('?', $url, 2);
        $base = $parts[0];
        $queryString = $parts[1] ?? '';
        parse_str($queryString, $queryParams);

        // Resolve UNIX timestamp from $modified
        $ts = null;
        if ($modified !== null && $modified !== '') {
            if (is_numeric($modified)) {
                $ts = (int)$modified;
            } else {
                $parsed = strtotime((string)$modified);
                if ($parsed !== false && $parsed > 0) {
                    $ts = $parsed;
                }
            }
        }

        // Fallback: check filemtime on disk if path points to local file
        if ($ts === null || $ts <= 0) {
            $cleanPath = ltrim(parse_url($base, PHP_URL_PATH) ?? '', '/\\');
            $fullPath = dirname(__DIR__, 2) . '/' . $cleanPath;
            if (is_file($fullPath)) {
                $mtime = @filemtime($fullPath);
                if ($mtime !== false && $mtime > 0) {
                    $ts = $mtime;
                }
            }
        }

        if ($ts === null || $ts <= 0) {
            $ts = time();
        }

        $queryParams['v'] = (string)$ts;
        return $base . '?' . http_build_query($queryParams);
    }

    /**
     * Resolves the disk path for a requested template name.
     */
    private static function resolveViewPath(string $viewName): string
    {
        $normalized = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $viewName), DIRECTORY_SEPARATOR);
        return self::$viewsDir . $normalized . '.php';
    }

    /**
     * Isolates variable scope and captures output using buffering.
     *
     * @param string $templatePath
     * @param array<string, mixed> $params
     * @return string
     */
    private static function evaluateTemplate(string $templatePath, array $params): string
    {
        extract($params, EXTR_SKIP);

        ob_start();
        try {
            require $templatePath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string)ob_get_clean();
    }
}