<?php

declare(strict_types=1);

namespace Bladewell;

use InvalidArgumentException;

/**
 * One entry of registry/{name}.json. Its files are not listed here: they are whatever sits in
 * resources/views/widget/{name} and resources/js/{name}, so the manifest can't drift from the source.
 */
final readonly class Widget
{
    /**
     * @param  list<string>  $requires  other widgets it renders or imports
     * @param  list<string>  $support  PHP helpers from src/Support
     * @param  list<string>  $rules  validation rules from src/Rules
     * @param  list<string>  $composer  Composer packages the user must install themselves
     * @param  list<string>  $phpExtensions
     * @param  list<string>  $examples  slugs of resources/examples/{name}/{slug}.blade.php, in display order
     * @param  list<string>  $usage  slugs of code-only snippets in resources/usage/{name}/, in display order
     * @param  array<string, array<string, string>>  $slots  by component tag: each named slot and what it's for
     * @param  string|null  $heading  for headings, when the name doesn't read right as one (otp → OTP)
     * @param  string|null  $group  the catalogue's heading it's listed under (Forms), for related widgets
     * @param  list<string>  $examplesUse  other widgets its examples use, not installed with it: the page names them
     * @param  list<Consideration>  $consider  what to weigh on the server when wiring it up: security and server cost
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $requires = [],
        public array $support = [],
        public array $rules = [],
        public array $composer = [],
        public array $phpExtensions = [],
        public array $examples = [],
        public array $usage = [],
        public array $slots = [],
        public ?string $heading = null,
        public ?string $group = null,
        public array $examplesUse = [],
        public array $consider = [],
    ) {}

    /** Date range picker, for headings: the manifest's title, or else the name. */
    public function title(): string
    {
        return $this->heading ?? ucfirst(str_replace('-', ' ', $this->name));
    }

    /**
     * @param  array<mixed>  $data  the decoded manifest
     */
    public static function fromManifest(string $name, array $data): self
    {
        $description = $data['description'] ?? null;
        if (!is_string($description) || $description === '') {
            throw new InvalidArgumentException("Widget [{$name}] needs a description.");
        }
        foreach (['title', 'group'] as $key) {
            if (isset($data[$key]) && (!is_string($data[$key]) || $data[$key] === '')) {
                throw new InvalidArgumentException("Widget [{$name}]: \"{$key}\" must be a non-empty string.");
            }
        }

        return new self(
            $name,
            $description,
            self::strings($name, $data, 'requires'),
            self::strings($name, $data, 'support'),
            self::strings($name, $data, 'rules'),
            self::strings($name, $data, 'composer'),
            self::strings($name, $data, 'php-extensions'),
            self::strings($name, $data, 'examples'),
            self::strings($name, $data, 'usage'),
            self::slots($name, $data),
            $data['title'] ?? null,
            $data['group'] ?? null,
            self::strings($name, $data, 'examples-use'),
            Consideration::listFromManifest($name, $data),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, array<string, string>>
     */
    private static function slots(string $name, array $data): array
    {
        $slots = $data['slots'] ?? [];
        $valid = is_array($slots) && !array_is_list($slots) || $slots === [];
        foreach (is_array($slots) ? $slots : [] as $tag => $named) {
            $valid = $valid && is_string($tag) && is_array($named) && $named !== [] && !array_is_list($named)
                && array_filter($named, is_string(...)) === $named;
        }
        if (!$valid) {
            throw new InvalidArgumentException("Widget [{$name}]: \"slots\" must map component tags to {\"slot\": \"what it's for\"}.");
        }

        /** @var array<string, array<string, string>> $slots */
        return $slots;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private static function strings(string $name, array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value) || !array_is_list($value) || array_filter($value, is_string(...)) !== $value) {
            throw new InvalidArgumentException("Widget [{$name}]: \"{$key}\" must be a list of strings.");
        }

        return $value;
    }
}
