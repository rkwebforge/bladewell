<?php

declare(strict_types=1);

namespace Bladewell\Console;

use Bladewell\Mcp\Server;
use Illuminate\Console\Command;
use JsonException;

/**
 * The MCP server over stdio, for AI agents: one JSON-RPC message per line in, one per line out. Add it to the agent
 * once, run from the app's root: claude mcp add bladewell -- php artisan bladewell:mcp
 */
final class McpCommand extends Command
{
    protected $signature = 'bladewell:mcp';

    protected $description = 'Run the Bladewell MCP server over stdio, for AI agents';

    public function handle(Server $server): int
    {
        // stdout carries the protocol and nothing else: a stray line of output would break the client's parser.
        $in = fopen('php://stdin', 'r');
        $out = fopen('php://stdout', 'w');
        if ($in === false || $out === false) {
            return self::FAILURE;
        }

        while (($line = fgets($in)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            try {
                $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $response = match (true) {
                    // A batch (protocol 2025-03-26 and before): an array of the answers, leaving out notifications'.
                    is_array($message) && $message !== [] && array_is_list($message) => array_values(array_filter(array_map(
                        static fn (mixed $one): ?array => is_array($one) && !array_is_list($one) ? $server->handle($one) : self::invalid(),
                        $message,
                    ))) ?: null,
                    is_array($message) && $message !== [] => $server->handle($message),
                    default => self::invalid(),
                };
            } catch (JsonException $e) {
                $response = ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error: '.$e->getMessage()]];
            }
            if ($response !== null) {
                fwrite($out, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                fflush($out);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private static function invalid(): array
    {
        return ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Invalid request: expected a JSON object.']];
    }
}
