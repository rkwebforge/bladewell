<?php

declare(strict_types=1);

namespace Bladewell\Mcp;

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
        .'Find components with list_components, read one with get_component (props, slots, examples, Livewire usage) before writing it into a view, and check project_status for what is installed and edited. '
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
                'description' => 'The Bladewell components, each with its description, group (Forms…), what it requires and whether this app has it installed. Narrow it with query, which matches names and descriptions.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => 'Words to look for, e.g. "date" or "upload".']],
                ],
                'annotations' => ['readOnlyHint' => true],
            ],
            [
                'name' => 'get_component',
                'title' => 'Get a component',
                'description' => 'Everything about one component: its Blade tags with every prop (default, required, what it does) and slot, its requirements, usage snippets (Livewire included) and working examples. Read it before writing the component into a view.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string', 'enum' => $names, 'description' => 'The component, e.g. "datepicker".']],
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
                'list_components' => $this->listComponents(is_string($arguments['query'] ?? null) ? $arguments['query'] : ''),
                'get_component' => $this->getComponent((string) ($arguments['name'] ?? '')),
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
    private function listComponents(string $query): array
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
     * @return array<string, mixed>
     */
    private function getComponent(string $name): array
    {
        $widget = $this->registry->get($name);

        return [
            'name' => $widget->name,
            'title' => $widget->title(),
            'install' => "php artisan bladewell:add {$widget->name}",
            'installed' => in_array($widget->name, $this->installer->installed(), true),
            ...(new WidgetDetails($this->registry))->describe($widget),
        ];
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

        return [
            'written' => $confirm,
            'next' => match (true) {
                !$confirm && $writes !== [] => 'Dry run: nothing was written. Show these files to the person; call again with confirm: true to install.',
                !$confirm => 'Nothing to write: everything named is already up to date.',
                $writes !== [] => 'Installed. Rebuild the assets: npm run build (or keep npm run dev running).',
                default => 'Already up to date: nothing was written.',
            },
            'components' => array_map(static fn (Widget $widget): string => $widget->name, $widgets),
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
