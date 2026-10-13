<?php

declare(strict_types=1);

namespace Bladewell\Console;

use Bladewell\FileStatus;
use Bladewell\Installer;
use Bladewell\InstallTarget;
use Bladewell\PlannedFile;
use Bladewell\Registry;
use Bladewell\Requirements;
use Bladewell\Widget;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Laravel\Prompts\MultiSearchPrompt;
use LogicException;
use Symfony\Component\Console\Terminal;

final class AddWidgetCommand extends Command
{
    protected $signature = 'bladewell:add
        {widgets?* : Widget names, e.g. select datepicker (see bladewell:list)}
        {--all : Install every widget}
        {--installed : Update every widget already in this app}
        {--force : Overwrite files you have edited too}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Copy widgets, and everything they need, into this app';

    public function handle(Registry $registry, InstallTarget $target): int
    {
        $installer = new Installer($registry, $target);

        /** @var list<string> $names */
        $names = $this->option('all') ? array_keys($registry->all()) : array_values((array) $this->argument('widgets'));
        if ($this->option('installed')) {
            $installed = $installer->installed();
            if ($installed === [] && $names === []) {
                $this->components->error('No widgets are installed yet. Add some first, e.g. php artisan bladewell:add '.array_key_first($registry->all()).'.');

                return self::INVALID;
            }
            $names = array_values(array_unique([...$names, ...$installed]));
        }
        if ($names === [] && $this->input->isInteractive()) {
            $names = $this->pick($registry);
        }
        if ($names === []) {
            $this->components->error('Name the widgets to add, or pass --all or --installed. Available: '.implode(', ', array_keys($registry->all())).'.');

            return self::INVALID;
        }

        // Before anything is written: on an older Tailwind the copy succeeds and every widget renders unstyled.
        $requirements = Requirements::check($target->basePath);
        foreach ($requirements->warnings as $warning) {
            $this->components->warn($warning);
        }
        if ($requirements->errors !== []) {
            foreach ($requirements->errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // The registry's own message lists every widget, which suits an agent over MCP but is a wall of text
        // here: name the closest instead, and where to see the rest.
        $unknown = array_values(array_diff($names, array_keys($registry->all())));
        if ($unknown !== []) {
            foreach ($unknown as $name) {
                $close = $this->closest($name, array_keys($registry->all()));
                $this->components->error("Unknown widget [{$name}].".($close === [] ? '' : ' Did you mean '.implode(' or ', $close).'?'));
            }
            $this->line('  See them all with php artisan bladewell:list, or run php artisan bladewell:add with no names to choose from a list.');

            return self::INVALID;
        }

        $requested = $names;
        try {
            $widgets = $registry->resolve($names);
        } catch (InvalidArgumentException|LogicException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $planned = $installer->plan($widgets, (bool) $this->option('force'));
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun) {
            $installer->apply($planned);
        }

        $count = static fn (FileStatus $status): int => count(array_filter($planned, static fn (PlannedFile $file): bool => $file->status === $status));
        $writes = $count(FileStatus::Create) + $count(FileStatus::Update);
        $skipped = $count(FileStatus::Conflict) + $count(FileStatus::Untracked);
        $names = implode(', ', array_map(static fn (Widget $widget): string => $widget->name, $widgets));

        // Say what actually happened: a re-run that writes nothing hasn't installed anything.
        $this->components->info(match (true) {
            $writes > 0 => ($dryRun ? 'Would install: ' : 'Installed: ').$names,
            $skipped > 0 => ($dryRun ? 'Nothing would be written for: ' : 'Nothing written for: ').$names,
            default => 'Already up to date: '.$names,
        });
        $this->broughtAlong($widgets, $requested);
        foreach ($planned as $file) {
            if ($file->status !== FileStatus::Unchanged || $this->output->isVerbose()) {
                $this->components->twoColumnDetail($this->relative($target, $file), $this->label($file->status));
            }
        }

        if (($edited = $count(FileStatus::Conflict)) > 0) {
            $this->components->warn("{$edited} file(s) you edited were left alone, so they miss this version's changes. See what changed with php artisan bladewell:diff, or re-run with --force to replace them.");
        }
        // These predate bladewell.lock, so there's no telling an edit from an older version.
        if (($untracked = $count(FileStatus::Untracked)) > 0) {
            $this->components->warn("{$untracked} file(s) differ from this version and were installed before bladewell.lock kept track, so they were left alone. If you haven't edited them, re-run with --force (it covers every widget you name, so name only those you haven't edited); from then on updates apply by themselves.");
        }

        $this->notes($widgets, $target, rebuild: !$dryRun && $writes > 0);

        return self::SUCCESS;
    }

    /**
     * With no names in a terminal, a list to tick rather than an error. Typing narrows it, so forty names
     * don't mean scrolling through forty.
     *
     * @return list<string>
     */
    private function pick(Registry $registry): array
    {
        // Names only: what each one needs comes along by itself, and the summary after says what and why.
        $names = array_keys($registry->all());

        // The hint is grey; the keys stand out in the prompt's cyan, then it goes back to grey. Prompts counts
        // those codes as width and cuts a hint wider than the terminal less 6, possibly mid-code, so a narrow
        // terminal gets it plain.
        $keys = ['Space', 'Enter', 'Ctrl+C'];
        $format = '%s to select, %s to install, %s to quit';
        $hint = vsprintf($format, $keys);
        $coloured = vsprintf($format, array_map(static fn (string $key): string => "\e[36m{$key}\e[90m", $keys));
        if (mb_strwidth($coloured) <= (new Terminal)->getWidth() - 6) {
            $hint = $coloured;
        }

        /** @var list<string> $picked */
        $picked = (new MultiSearchPrompt(
            label: 'Which widgets do you want to add? Select as many as you like.',
            // Laravel's fallback for Windows asks for the query as plain text, and an empty answer comes as null.
            options: static fn (?string $query): array => array_values(array_filter(
                $names,
                static fn (string $name): bool => str_contains($name, strtolower(trim((string) $query))),
            )),
            placeholder: 'Type to filter',
            scroll: 12,
            required: 'Select at least one, or press Ctrl+C to stop.',
            hint: $hint,
        ))->prompt();

        return array_values($picked);
    }

    /**
     * Up to two names a typo or a part was likely meant as: "datepiker" or "date" finds datepicker. Two letters swapped
     * count as two edits, so "tabel" needs two to find table.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function closest(string $typed, array $names): array
    {
        $typed = strtolower(trim($typed));
        $distances = [];
        foreach ($names as $name) {
            $distance = levenshtein($typed, $name);
            if (($typed !== '' && str_contains($name, $typed)) || $distance <= max(strlen($typed) >= 4 ? 2 : 1, intdiv(strlen($typed), 3))) {
                $distances[$name] = $distance;
            }
        }
        asort($distances);

        return array_slice(array_keys($distances), 0, 2);
    }

    /**
     * Why each widget nobody named is coming along: "modal, for pagination". The flat list alone doesn't
     * explain why two names install seven widgets.
     *
     * @param  list<Widget>  $widgets  resolved, dependencies first
     * @param  list<string>  $requested
     */
    private function broughtAlong(array $widgets, array $requested): void
    {
        $lines = [];
        foreach ($widgets as $widget) {
            if (in_array($widget->name, $requested, true)) {
                continue;
            }
            $for = array_map(
                static fn (Widget $other): string => $other->name,
                array_filter($widgets, static fn (Widget $other): bool => in_array($widget->name, $other->requires, true)),
            );
            $lines[] = "{$widget->name}, for ".implode(', ', $for);
        }

        if ($lines !== []) {
            $this->line('  Also brings:');
            $this->components->bulletList($lines);
        }
    }

    /**
     * Things the installer can't do for the user: packages and extensions to install, rules to use.
     *
     * @param  list<Widget>  $widgets
     */
    private function notes(array $widgets, InstallTarget $target, bool $rebuild): void
    {
        $packages = array_unique(array_merge(...array_map(static fn (Widget $widget): array => $widget->composer, $widgets)));
        $missing = array_filter($packages, static fn (string $package): bool => !InstalledVersions::isInstalled($package));
        if ($missing !== []) {
            $this->components->warn('Install the required packages: composer require '.implode(' ', $missing));
        }

        $extensions = array_unique(array_merge(...array_map(static fn (Widget $widget): array => $widget->phpExtensions, $widgets)));
        foreach (array_filter($extensions, static fn (string $extension): bool => !extension_loaded($extension)) as $extension) {
            $this->components->warn("Enable the PHP {$extension} extension.");
        }

        // Only the hints for rules these widgets actually bring: the input's Captcha has nothing to do with dates.
        $rules = array_unique(array_merge(...array_map(static fn (Widget $widget): array => $widget->rules, $widgets)));
        $hints = [];
        if (in_array('NotAfterToday', $rules, true)) {
            $minimumAge = in_array('MinimumAge', $rules, true) ? ' (or MinimumAge for birthdays)' : '';
            $hints[] = "Validate submitted dates with new \\{$target->rulesNamespace}\\NotAfterToday{$minimumAge}, not before_or_equal:today.";
        }
        if (in_array('Captcha', $rules, true)) {
            $hints[] = "For a Turnstile, reCAPTCHA or hCaptcha field, check the token with new \\{$target->rulesNamespace}\\Captcha('turnstile'); its keys go in config/services.php.";
        }
        if ($rebuild) {
            $hints[] = 'Rebuild your assets: npm run build (or keep npm run dev running).';
        }
        if ($hints !== []) {
            $this->components->bulletList($hints);
        }
    }

    private function label(FileStatus $status): string
    {
        return match ($status) {
            FileStatus::Create => '<fg=green>create</>',
            FileStatus::Update => '<fg=yellow>update</>',
            FileStatus::Unchanged => '<fg=gray>unchanged</>',
            FileStatus::Conflict => '<fg=red>edited, skipped</>',
            FileStatus::Untracked => '<fg=red>differs, skipped</>',
        };
    }

    private function relative(InstallTarget $target, PlannedFile $file): string
    {
        return str_starts_with($file->path, $target->basePath.'/') ? substr($file->path, strlen($target->basePath) + 1) : $file->path;
    }
}
