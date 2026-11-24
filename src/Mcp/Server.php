<?php

declare(strict_types=1);

namespace Bladewell\Mcp;

use Bladewell\Consideration;
use Bladewell\FileStatus;
use Bladewell\Installer;
use Bladewell\InstallTarget;
use Bladewell\PlannedFile;
use Bladewell\Registry;
use Bladewell\Requirements;
use Bladewell\Widget;
use Bladewell\WidgetDetails;
use Composer\InstalledVersions;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * A Model Context Protocol server for one Laravel app: the catalogue, what this app has installed (and edited), and
 * installing, which only writes after a dry run has been seen and confirmed. JSON-RPC 2.0, one message in, at most one
 * out; bladewell:mcp carries them over stdio. Nothing here talks to the network.
 */
final class Server
{
    /** Newest first; a client asking for one of these gets it, anything else gets the newest. */
    private const array PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private const string INSTRUCTIONS = 'Bladewell copies Blade + Tailwind CSS v4 components into this Laravel app, where they become the app\'s own files, used as <x-widget.{name}>. '
        // Models that don't know the name yet take it for a typo of the older library and write its tags from memory.
        .'Bladewell is not BladewindUI, a different library: never write <x-bladewind.*> tags or follow its docs here. '
        .'Find components with list_components, read one with get_component (props, slots, examples, Livewire usage) before writing it into a view, and check project_status for what is installed and edited. '
        // Every answer stays in the agent's context and is sent again each turn: the whole list is ~7K tokens, a table ~9K.
        .'To keep the context small, give list_components a query (brief: true for a line each), or skip it when the person named the components; call get_component once for each component you use, as its answer does not change, with brief: true, then ask for the examples or usage you need by their titles in more-examples and more-usage; once installed, the component\'s files in the app are its source. '
        .'Before writing the routes a component calls or submits to, read its consider notes in get_component (security, and server cost such as paging and caching): apply what fits the app, and tell the person which you left out. '
        .'add_components without confirm is a dry run: show the person its file list, and call it again with confirm: true only once they agree. It never overwrites files they edited unless force is true. '
        .'After installing, the assets need rebuilding (npm run build, or a running npm run dev).';

    private readonly Installer $installer;

    public function __construct(
        private readonly Registry $registry,
        private readonly InstallTarget $target,
    ) {
        $this->installer = new Installer($registry, $target);
    }

    /**
     * One JSON-RPC message in; the response, or null for a notification (which gets none).
     *
     * @param  array<mixed>  $message
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $isRequest = array_key_exists('id', $message);

        if (!is_string($method) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return $isRequest ? $this->error($id, -32600, 'Invalid request: needs jsonrpc "2.0" and a method.') : null;
        }
        if (!$isRequest) {
            // notifications/initialized, notifications/cancelled…: nothing to answer.
            return null;
        }

        try {
            return match ($method) {
                'initialize' => $this->result($id, $this->initialize($params)),
                'ping' => $this->result($id, (object) []),
                'tools/list' => $this->result($id, ['tools' => $this->tools()]),
                'tools/call' => $this->result($id, $this->call($params)),
                default => $this->error($id, -32601, "Method not found: {$method}"),
            };
        } catch (Throwable $e) {
            return $this->error($id, -32603, 'Internal error: '.$e->getMessage());
        }
    }

    /**
     * @param  array<mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $asked = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($asked, self::PROTOCOL_VERSIONS, true) ? $asked : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => 'bladewell', 'title' => 'Bladewell', 'version' => $this->version()],
            'instructions' => self::INSTRUCTIONS,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        $names = array_keys($this->registry->all());

        return [
            [
                'name' => 'list_components',
                'title' => 'List components',
                'description' => 'The Bladewell components, each with its description, group (Forms…), what it requires and whether this app has it installed. The whole list is long: pass query, which matches names and descriptions, whenever you know what you need.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Words to look for, e.g. "date" or "upload".'],
                        'brief' => ['type' => 'boolean', 'default' => false, 'description' => 'Each component as name, group, a one-sentence summary and whether it is installed: about a third of the size.'],
                    ],
                ],
                'annotations' => ['readOnlyHint' => true],
            ],
            [
                'name' => 'get_component',
                'title' => 'Get a component',
                'description' => 'Everything about one component: its Blade tags with every prop (default, required, what it does) and slot, its requirements, usage snippets (Livewire included) and working examples. Read it before writing the component into a view.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'enum' => $names, 'description' => 'The component, e.g. "datepicker".'],
                        'brief' => ['type' => 'boolean', 'default' => false, 'description' => 'Only the first example and the first usage snippet that isn\'t Livewire (often the controller the routes need); more-examples and more-usage list the rest, each with its title and first sentence. Props, slots and consider notes are always whole.'],
                        'examples' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Titles of the examples to give in full, e.g. ["Filters"]; [] for none. Without it, all of them (the first with brief).'],
                        'usage' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Titles of the usage snippets to give in full, e.g. ["Livewire"]; [] for none. Without it, all of them (the first that isn\'t Livewire with brief).'],
                    ],
                    'required' => ['name'],
                ],
                'annotations' => ['readOnlyHint' => true],
            ],
            [
                'name' => 'project_status',
                'title' => 'Project status',
                'description' => 'What this app has installed, and which installed files are out of date (a newer version is available), edited by the developer (left alone by updates) or from before bladewell.lock. Also whether the app\'s Tailwind CSS and Vite are what the components need.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
                'annotations' => ['readOnlyHint' => true],
            ],
            [
                'name' => 'add_components',
                'title' => 'Add components',
                'description' => 'Copies components, and what they require, into this app. Without confirm it is a dry run that writes nothing and lists every file it would create or update; show that to the person, and only call again with confirm: true once they agree. Files the developer edited are skipped unless force is true. Also updates installed components to this version.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'components' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $names], 'minItems' => 1, 'description' => 'Names, e.g. ["select", "datepicker"].'],
                        'confirm' => ['type' => 'boolean', 'default' => false, 'description' => 'Write the files. Only after the person has seen the dry run and agreed.'],
                        'force' => ['type' => 'boolean', 'default' => false, 'description' => 'Overwrite files the developer edited in the components named too (never the theme, nor components brought along for them). Only when they ask for it.'],
                    ],
                    'required' => ['components'],
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
            ],
        ];
    }

    /**
     * @param  array<mixed>  $params
     * @return array<string, mixed>
     */
    private function call(array $params): array
    {
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $data = match ($params['name'] ?? null) {
                'list_components' => $this->listComponents(is_string($arguments['query'] ?? null) ? $arguments['query'] : '', ($arguments['brief'] ?? false) === true),
                'get_component' => $this->getComponent((string) ($arguments['name'] ?? ''), $arguments),
                'project_status' => $this->projectStatus(),
                'add_components' => $this->addComponents($arguments),
                default => throw new InvalidArgumentException('Unknown tool: '.json_encode($params['name'] ?? null).'. See tools/list.'),
            };
        } catch (InvalidArgumentException|LogicException $e) {
            // The tool ran and said no (an unknown component, an old Tailwind): the agent can read it and try again.
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        // Compact: most agents read the text, where indentation only costs tokens.
        return [
            'content' => [['type' => 'text', 'text' => (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
            'structuredContent' => $data,
        ];
    }

    // --- Tools -------------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function listComponents(string $query, bool $brief = false): array
    {
        $installed = $this->installer->installed();
        $words = array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: []);
        $matches = array_filter($this->registry->all(), static function (Widget $widget) use ($words): bool {
            $haystack = mb_strtolower("{$widget->name} {$widget->title()} {$widget->group} {$widget->description}");

            // Each word at the start of a word there: "date" finds the date pickers, not "updates".
            foreach ($words as $word) {
                if (preg_match('/\\b'.preg_quote($word, '/').'/u', $haystack) !== 1) {
                    return false;
                }
            }

            return true;
        });

        if ($brief) {
            return ['components' => array_values(array_map(static fn (Widget $widget): array => [
                'name' => $widget->name,
                'group' => $widget->group,
                // The first sentence: the rest of a description is what get_component is for.
                'summary' => self::firstSentence($widget->description),
                'installed' => in_array($widget->name, $installed, true),
            ], $matches))];
        }

        return ['components' => array_values(array_map(static fn (Widget $widget): array => [
            'name' => $widget->name,
            'title' => $widget->title(),
            'tag' => "<x-widget.{$widget->name}>",
            'group' => $widget->group,
            'description' => $widget->description,
            'requires' => $widget->requires,
            'installed' => in_array($widget->name, $installed, true),
        ], $matches))];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getComponent(string $name, array $arguments = []): array
    {
        $widget = $this->registry->get($name);
        $component = [
            'name' => $widget->name,
            'title' => $widget->title(),
            'install' => "php artisan bladewell:add {$widget->name}",
            'installed' => in_array($widget->name, $this->installer->installed(), true),
            ...(new WidgetDetails($this->registry))->describe($widget),
        ];

        $brief = ($arguments['brief'] ?? false) === true;
        if (!$brief && !array_key_exists('examples', $arguments) && !array_key_exists('usage', $arguments)) {
            // Asked as before: the same answer as before, with no more-* keys.
            return $component;
        }

        /** @var list<array{title: string, description?: string|null}> $examples */
        $examples = $component['examples'];
        /** @var list<array{title: string, description?: string|null}> $usage */
        $usage = $component['usage'];
        $firstExample = static fn (array $items): array => array_slice($items, 0, 1);
        // The first snippet that isn't Livewire: the controller, Form Request or layout line the examples send people
        // to ("as Usage shows"), where the safe way to write the route is. An app without Livewire never needs those.
        $firstUsage = static fn (array $items): array => array_slice(array_values(array_filter($items, static fn (array $item): bool => !str_contains(mb_strtolower($item['title']), 'livewire'))), 0, 1);

        [$component['examples'], $component['more-examples']] = $this->pick($examples, $arguments['examples'] ?? null, $brief ? $firstExample : null, 'example', $widget->name);
        [$component['usage'], $component['more-usage']] = $this->pick($usage, $arguments['usage'] ?? null, $brief ? $firstUsage : null, 'usage snippet', $widget->name);

        return $component;
    }

    /**
     * The items asked for by title (or, when none were named, those $byDefault keeps, or all when it is null), and the
     * rest as title and first sentence, so an agent can tell which to ask for next.
     *
     * @param  list<array{title: string, description?: string|null}>  $items
     * @param  (callable(list<array{title: string, description?: string|null}>): list<array{title: string, description?: string|null}>)|null  $byDefault
     * @return array{0: list<array{title: string, description?: string|null}>, 1: list<array{title: string, summary: string|null}>}
     */
    private function pick(array $items, mixed $titles, ?callable $byDefault, string $kind, string $widget): array
    {
        // "Livewire view", "livewire-view" and "LIVEWIRE VIEW" all name the same snippet: titles come from file names.
        $key = static fn (string $title): string => mb_strtolower(str_replace('-', ' ', trim($title)));

        // One title sent bare, as agents sometimes do: ignoring it would send back everything, the most tokens of all.
        if (is_string($titles)) {
            $titles = [$titles];
        }

        if (is_array($titles)) {
            $known = array_map(static fn (array $item): string => $key($item['title']), $items);
            $wanted = [];
            $unknown = [];
            foreach ($titles as $title) {
                if (is_string($title) && in_array($key($title), $known, true)) {
                    $wanted[] = $key($title);
                } else {
                    // As sent, so the agent recognises what it asked for.
                    $unknown[] = (string) json_encode($title, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
            if ($unknown !== []) {
                $available = implode(', ', array_map(static fn (array $item): string => '"'.$item['title'].'"', $items)) ?: 'none';
                throw new InvalidArgumentException("No {$kind} of {$widget} is called ".implode(', ', $unknown).". Its {$kind}s: {$available}.");
            }
            $chosen = array_filter($items, static fn (array $item): bool => in_array($key($item['title']), $wanted, true));
        } else {
            $chosen = $byDefault === null ? $items : $byDefault($items);
        }

        $rest = array_udiff($items, $chosen, static fn (array $a, array $b): int => strcmp($a['title'], $b['title']));

        // A title alone ("Compact", "Stacked") rarely says whether it fits the task: the first sentence does, for a few tokens.
        return [array_values($chosen), array_values(array_map(static fn (array $item): array => [
            'title' => $item['title'],
            'summary' => self::firstSentence($item['description'] ?? null),
        ], $rest))];
    }

    private static function firstSentence(?string $text): ?string
    {
        return $text === null || $text === '' ? null : (preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', $text, 2)[0] ?? $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectStatus(): array
    {
        $installed = $this->installer->installed();
        $requirements = Requirements::check($this->target->basePath);
        $files = $installed === [] ? [] : $this->installer->plan($this->registry->resolve($installed));
        $by = fn (FileStatus $status): array => array_values(array_map(
            $this->relative(...),
            array_filter($files, fn (PlannedFile $file): bool => $file->status === $status && !$this->installer->isEntry($file->path)),
        ));

        return [
            'installed' => $installed,
            // What add_components on the installed ones would bring: newer versions, and files gone missing.
            'outdated' => $by(FileStatus::Update),
            'missing' => $by(FileStatus::Create),
            // Left alone by updates; bladewell:diff shows how they differ.
            'edited' => $by(FileStatus::Conflict),
            'untracked' => $by(FileStatus::Untracked),
            'requirements' => ['errors' => $requirements->errors, 'warnings' => $requirements->warnings],
        ];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array<string, mixed>
     */
    private function addComponents(array $arguments): array
    {
        $names = array_values(array_filter((array) ($arguments['components'] ?? []), is_string(...)));
        if ($names === []) {
            throw new InvalidArgumentException('Name the components to add, e.g. {"components": ["select"]}. list_components shows them.');
        }
        $confirm = ($arguments['confirm'] ?? false) === true;
        $force = ($arguments['force'] ?? false) === true;

        // Before anything is written, as bladewell:add does: on an older Tailwind every component installs and looks broken.
        $requirements = Requirements::check($this->target->basePath);
        if ($requirements->errors !== []) {
            throw new InvalidArgumentException(implode(' ', $requirements->errors));
        }

        $widgets = $this->registry->resolve($names);
        $planned = $this->installer->plan($widgets, $force ? $names : []);
        if ($confirm) {
            $this->installer->apply($planned);
        }
        $writes = array_filter($planned, static fn (PlannedFile $file): bool => $file->status->writes());
        // The named components only: a table brings select and search along, but their routes aren't the table's.
        $consider = [];
        foreach ($widgets as $widget) {
            if (in_array($widget->name, $names, true) && $widget->consider !== []) {
                $consider[$widget->name] = array_map(static fn (Consideration $item): array => $item->toArray(), $widget->consider);
            }
        }

        return [
            'written' => $confirm,
            'next' => match (true) {
                !$confirm && $writes !== [] => 'Dry run: nothing was written. Show these files to the person; call again with confirm: true to install.',
                !$confirm => 'Nothing to write: everything named is already up to date.',
                $writes !== [] => 'Installed. Rebuild the assets: npm run build (or keep npm run dev running).'.($consider === [] ? '' : ' Before writing the routes behind them, weigh each note in consider.'),
                default => 'Already up to date: nothing was written.',
            },
            'components' => array_map(static fn (Widget $widget): string => $widget->name, $widgets),
            // Additive: by component, the notes for the routes behind it (as in get_component); an object even when empty.
            'consider' => (object) $consider,
            'broughtAlong' => array_values(array_diff(array_map(static fn (Widget $widget): string => $widget->name, $widgets), $names)),
            'files' => array_values(array_map(fn (PlannedFile $file): array => ['path' => $this->relative($file), 'status' => $file->status->value], array_filter($planned, static fn (PlannedFile $file): bool => $file->status !== FileStatus::Unchanged))),
            'warnings' => array_values(array_filter([
                ...$requirements->warnings,
                array_filter($planned, static fn (PlannedFile $file): bool => $file->status === FileStatus::Conflict) !== []
                    ? 'Some files were edited by the developer and are left alone (status "conflict"). Pass force: true only if they want them replaced; php artisan bladewell:diff shows the differences.'
                    : null,
            ])),
        ];
    }

    // --- JSON-RPC --------------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function relative(PlannedFile $file): string
    {
        return str_starts_with($file->path, $this->target->basePath.'/') ? substr($file->path, strlen($this->target->basePath) + 1) : $file->path;
    }

    private function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('bladewell/bladewell') ?? 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
