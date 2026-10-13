<?php

declare(strict_types=1);

namespace Bladewell\Console;

use Bladewell\Registry;
use Bladewell\Widget;
use Bladewell\WidgetDetails;
use Illuminate\Console\Command;

final class ListWidgetsCommand extends Command
{
    protected $signature = 'bladewell:list {--json : Full registry as JSON: components and their props, requirements and usage examples}';

    protected $description = 'List the widgets that bladewell:add can install';

    public function handle(Registry $registry, WidgetDetails $details): int
    {
        $widgets = $registry->all();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map($details->describe(...), $widgets),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        // Descriptions run to a paragraph and would make each row wider than the terminal; they're in --json
        // and on the site.
        $this->table(
            // Not "Requires": that reads as something to install yourself, and add brings these along.
            ['Widget', 'Also installs'],
            array_map(static fn (Widget $widget): array => [
                $widget->name,
                implode(', ', $widget->requires) ?: '-',
            ], array_values($widgets)),
        );

        $this->newLine();
        $this->line('  Add some: php artisan bladewell:add select datepicker');
        $this->line('  What each does: https://www.bladewellui.com, or bladewell:list --json');

        return self::SUCCESS;
    }
}
