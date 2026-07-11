<?php

declare(strict_types=1);

namespace Syriable\Metrics\Console\Generators;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

/**
 * Turns the raw name a developer types on the command line ("Revenue",
 * "Sales/Revenue", "Sales\Revenue") into a fully-qualified class name that
 * respects the configured metrics namespace, and validates it is a name
 * PHP will actually accept.
 */
final readonly class NamespaceResolver
{
    /**
     * PHP's reserved words, plus the scalar/pseudo-type and literal names
     * (bool, true, null, …) PHP also forbids as a class name — none of
     * these may be used as a class (or namespace segment) name.
     *
     * @var list<string>
     */
    private const RESERVED_NAMES = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif',
        'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval',
        'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface',
        'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'numeric',
        'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'resource', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true',
        'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    public function __construct(private ConfigRepository $config) {}

    /**
     * The configured root namespace generated classes are rooted under,
     * e.g. "App\Metrics".
     */
    public function rootNamespace(): string
    {
        return trim((string) $this->config->get('metrics.generator.namespace', 'App\\Metrics'), '\\');
    }

    /**
     * Whether every segment of the given name is a syntactically valid,
     * non-reserved PHP class/namespace identifier.
     */
    public function isValidName(string $name): bool
    {
        $segments = $this->segments($name);

        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $segment) !== 1) {
                return false;
            }

            if (in_array(strtolower($segment), self::RESERVED_NAMES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The fully-qualified class name for the given input, rooted under the
     * configured namespace unless it is already fully qualified.
     */
    public function qualifyClass(string $name): string
    {
        $name = ltrim(str_replace('/', '\\', trim($name)), '\\');

        if (Str::startsWith($name, $this->rootNamespace().'\\') || $name === $this->rootNamespace()) {
            return $name;
        }

        return $this->rootNamespace().'\\'.$name;
    }

    /**
     * The short class name (no namespace) for the given input.
     */
    public function classBasename(string $name): string
    {
        return Str::afterLast($this->qualifyClass($name), '\\');
    }

    /**
     * The namespace (without the class itself) for the given input.
     */
    public function namespaceFor(string $name): string
    {
        return Str::beforeLast($this->qualifyClass($name), '\\');
    }

    /**
     * @return list<string>
     */
    private function segments(string $name): array
    {
        $normalized = trim(str_replace('/', '\\', trim($name)), '\\');

        if ($normalized === '') {
            return [];
        }

        return explode('\\', $normalized);
    }
}
