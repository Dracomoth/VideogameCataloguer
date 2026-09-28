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