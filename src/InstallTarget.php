<?php

declare(strict_types=1);

namespace Bladewell;

use InvalidArgumentException;

/**
 * Absolute destinations in the user's project, resolved once from config/bladewell.php.
 */
final readonly class InstallTarget
{
    public function __construct(
        public string $basePath,
        public string $viewsPath,
        public string $jsPath,
        public string $cssPath,
        public string $jsEntry,
        public string $cssEntry,
        public string $supportNamespace,
        public string $supportPath,
        public string $rulesNamespace,
        public string $rulesPath,
    ) {}

    /**
     * @param  array{support_namespace: string, rules_namespace: string, paths: array{js: string, css: string, js_entry: string, css_entry: string}}  $config
     */
    public static function fromConfig(string $basePath, array $config): self
    {
        $basePath = rtrim($basePath, '/');
        $roots = self::psr4Roots($basePath);
        $support = trim($config['support_namespace'], '\\');
        $rules = trim($config['rules_namespace'], '\\');

        return new self(
            $basePath,
            $basePath.'/resources/views/components/widget',
            $basePath.'/'.trim($config['paths']['js'], '/'),
            $basePath.'/'.trim($config['paths']['css'], '/'),
            $basePath.'/'.ltrim($config['paths']['js_entry'], '/'),
            $basePath.'/'.ltrim($config['paths']['css_entry'], '/'),
            $support,
            self::pathFor($support, $roots, $basePath),
            $rules,
            self::pathFor($rules, $roots, $basePath),
        );
    }

    /**
     * @return array<string, string> namespace prefix (no trailing \) => directory
     */
    private static function psr4Roots(string $basePath): array
    {
        $composer = json_decode((string) @file_get_contents($basePath.'/composer.json'), true);
        $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];

        $roots = [];
        foreach (is_array($psr4) ? $psr4 : [] as $prefix => $dirs) {
            // A namespace may map to a list of directories ("App\\": ["app/", "src/"]); new files go in the first.
            $dir = is_array($dirs) ? reset($dirs) : $dirs;
            if (is_string($prefix) && is_string($dir)) {
                $roots[trim($prefix, '\\')] = trim($dir, '/');
            }
        }

        return $roots;
    }

    /**
     * App\View\Widget with "App\\": "app/" → {base}/app/View/Widget. The longest matching prefix wins.
     *
     * @param  array<string, string>  $roots
     */
    private static function pathFor(string $namespace, array $roots, string $basePath): string
    {
        $match = null;
        foreach (array_keys($roots) as $prefix) {
            if (($namespace === $prefix || str_starts_with($namespace, $prefix.'\\'))
                && ($match === null || strlen($prefix) > strlen($match))) {
                $match = $prefix;
            }
        }

        if ($match === null) {
            throw new InvalidArgumentException("Namespace [{$namespace}] is not under any PSR-4 root in composer.json.");
        }

        $rest = str_replace('\\', '/', substr($namespace, strlen($match) + 1));

        return rtrim($basePath.'/'.$roots[$match].'/'.$rest, '/');
    }
}
