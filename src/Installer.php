<?php

declare(strict_types=1);

namespace Bladewell;

use Illuminate\Filesystem\Filesystem;

/**
 * Works out what copying a set of widgets into the project would change, then writes it.
 * Planning is separate from writing, so --dry-run shows exactly what a real install would do.
 *
 * The source runs as-is inside the package, under the package namespace; the copy is rewritten
 * into the app namespace from config/bladewell.php.
 */
final class Installer
{
    private const string SUPPORT_NAMESPACE = 'Bladewell\\Support';

    private const string RULES_NAMESPACE = 'Bladewell\\Rules';

    /** Every widget styles itself with these, so they come along with the first one installed. */
    private const array CSS = ['theme.css', 'base.css'];

    private readonly InstallLock $lock;

    public function __construct(
        private readonly Registry $registry,
        private readonly InstallTarget $target,
        private readonly Filesystem $files = new Filesystem,
    ) {
        $this->lock = new InstallLock($target->basePath, $files);
    }

    /**
     * @param  list<Widget>  $widgets  already resolved, see Registry::resolve()
     * @param  list<string>  $force  widgets whose own files overwrite the user's changes: their views, scripts and the
     *                               helpers and rules they declare. Never the theme, nor a widget brought along for them,
     *                               which the user may have edited for every widget that uses it.
     * @return list<PlannedFile>
     */
    public function plan(array $widgets, array $force = []): array
    {
        $forced = $this->sources(array_values(array_filter($widgets, static fn (Widget $widget): bool => in_array($widget->name, $force, true))), css: false);
        $planned = [];
        foreach ($this->files($widgets) as $path => $contents) {
            $planned[] = $this->compare($path, $contents, isset($forced[$path]));
        }

        $jsWidgets = array_values(array_filter($widgets, fn (Widget $widget): bool => $this->registry->files($widget, 'js') !== []));
        $planned[] = $this->patchJsEntry(array_map(fn (Widget $widget): string => $widget->name, $jsWidgets));
        $planned[] = $this->patchCssEntry();

        return $planned;
    }

    /**
     * Every file the widgets bring, as it lands in the app: absolute target path => contents.
     *
     * @param  list<Widget>  $widgets
     * @return array<string, string>
     */
    public function files(array $widgets): array
    {
        return array_map(fn (string $source): string => $this->render($this->files->get($source)), $this->sources($widgets));
    }

    /**
     * Where each of those files comes from: absolute target path => package path.
     *
     * @param  list<Widget>  $widgets
     * @return array<string, string>
     */
    private function sources(array $widgets, bool $css = true): array
    {
        $sources = [];
        foreach ($css ? self::CSS : [] as $file) {
            $sources[$this->target->cssPath.'/'.$file] = $this->registry->path("resources/css/{$file}");
        }
        foreach ($widgets as $widget) {
            foreach ($this->registry->files($widget, 'views') as $file) {
                $sources["{$this->target->viewsPath}/{$widget->name}/{$file}"] = $this->registry->dir($widget, 'views').'/'.$file;
            }
            foreach ($this->registry->files($widget, 'js') as $file) {
                $sources["{$this->target->jsPath}/{$widget->name}/{$file}"] = $this->registry->dir($widget, 'js').'/'.$file;
            }
            foreach ($widget->support as $class) {
                $sources["{$this->target->supportPath}/{$class}.php"] = $this->registry->path("src/Support/{$class}.php");
            }
            foreach ($widget->rules as $class) {
                $sources["{$this->target->rulesPath}/{$class}.php"] = $this->registry->path("src/Rules/{$class}.php");
            }
        }

        return $sources;
    }

    /**
     * Widgets already in the app, by their views folder: every widget has one, and it is there for copies made
     * before bladewell.lock too.
     *
     * @return list<string>
     */
    public function installed(): array
    {
        return array_values(array_filter(array_keys($this->registry->all()), fn (string $name): bool => $this->files->isDirectory("{$this->target->viewsPath}/{$name}")));
    }

    /**
     * Whether $path is app.js or app.css, which are patched in place rather than replaced.
     */
    public function isEntry(string $path): bool
    {
        return $path === $this->target->jsEntry || $path === $this->target->cssEntry;
    }

    /**
     * @param  list<PlannedFile>  $planned
     */
    public function apply(array $planned): void
    {
        foreach ($planned as $file) {
            if ($file->status->writes()) {
                $this->files->ensureDirectoryExists(dirname($file->path));
                $this->files->put($file->path, $file->contents);
            }
            // Unchanged counts too: it gives a copy made before the lock existed a record to compare against.
            // The entry files aren't recorded, because they are patched in place rather than replaced.
            if ($file->status !== FileStatus::Conflict && $file->status !== FileStatus::Untracked && !$this->isEntry($file->path)) {
                $this->lock->record($file->path, $file->contents);
            }
        }
        $this->lock->save();
    }

    private function render(string $source): string
    {
        return strtr($source, [
            self::SUPPORT_NAMESPACE => $this->target->supportNamespace,
            self::RULES_NAMESPACE => $this->target->rulesNamespace,
        ]);
    }

    private function compare(string $path, string $contents, bool $force): PlannedFile
    {
        $current = $this->files->exists($path) ? $this->files->get($path) : null;

        $status = match (true) {
            $current === null => FileStatus::Create,
            $current === $contents => FileStatus::Unchanged,
            $force => FileStatus::Update,
            // Still exactly as last installed, so the difference is a newer package version, not an edit.
            $this->lock->matches($path, $current) => FileStatus::Update,
            $this->lock->tracks($path) => FileStatus::Conflict,
            default => FileStatus::Untracked,
        };

        return new PlannedFile($path, $contents, $status);
    }

    /**
     * Adds `import './widget/{name}';` for each widget with JS, next to the imports already there.
     *
     * @param  list<string>  $names
     */
    private function patchJsEntry(array $names): PlannedFile
    {
        $path = $this->target->jsEntry;
        $original = $this->files->exists($path) ? $this->files->get($path) : '';
        $dir = self::relative(dirname($path), $this->target->jsPath);
        $contents = $original;

        foreach ($names as $name) {
            $specifier = preg_quote("{$dir}/{$name}", '/');
            if (preg_match("/^import\\s+['\"]{$specifier}(\\/index(\\.js)?)?['\"];?\\s*$/m", $contents) === 1) {
                continue;
            }
            $contents = $this->insertAfterLast($contents, '/^import\s+[\'"]'.preg_quote($dir, '/').'\//m', "import '{$dir}/{$name}';");
        }

        return $this->entry($path, $original, $contents);
    }

    /**
     * Adds the theme and base imports straight after `@import "tailwindcss";`, which must come first.
     */
    private function patchCssEntry(): PlannedFile
    {
        $path = $this->target->cssEntry;
        $original = $this->files->exists($path) ? $this->files->get($path) : "@import \"tailwindcss\";\n";
        $dir = self::relative(dirname($path), $this->target->cssPath);
        $contents = $original;

        foreach (self::CSS as $css) {
            $line = "@import \"{$dir}/{$css}\";";
            if (preg_match('/^@import\s+[\'"]'.preg_quote("{$dir}/{$css}", '/').'[\'"]/m', $contents) === 1) {
                continue;
            }
            $contents = $this->insertAfterLast($contents, '/^@import\s+[\'"](tailwindcss|'.preg_quote($dir, '/').'\/)/m', $line, prepend: true);
        }

        return $this->entry($path, $original, $contents);
    }

    private function entry(string $path, string $original, string $contents): PlannedFile
    {
        $status = match (true) {
            !$this->files->exists($path) => FileStatus::Create,
            $original === $contents => FileStatus::Unchanged,
            default => FileStatus::Update,
        };

        return new PlannedFile($path, $contents, $status);
    }

    /**
     * Puts $line on its own line after the last line matching $pattern; failing that, at the end
     * (or the start, with $prepend).
     */
    private function insertAfterLast(string $contents, string $pattern, string $line, bool $prepend = false): string
    {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $offset = end($matches[0])[1];
            $end = strpos($contents, "\n", $offset);

            return $end === false
                ? $contents."\n".$line."\n"
                : substr($contents, 0, $end + 1).$line."\n".substr($contents, $end + 1);
        }

        if ($prepend) {
            return $line."\n".$contents;
        }

        return ($contents === '' || str_ends_with($contents, "\n") ? $contents : $contents."\n").$line."\n";
    }

    /**
     * The import specifier from one directory to another: ./widget, ../widget, …
     */
    private static function relative(string $from, string $to): string
    {
        $notEmpty = static fn (string $part): bool => $part !== '';
        $from = array_values(array_filter(explode('/', $from), $notEmpty));
        $to = array_values(array_filter(explode('/', $to), $notEmpty));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $up = $from === [] ? '.' : implode('/', array_fill(0, count($from), '..'));

        return $to === [] ? $up : $up.'/'.implode('/', $to);
    }
}
