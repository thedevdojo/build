<?php

namespace App\Ai;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Sandboxed access to the target app's codebase.
 *
 * Every path is validated against the workspace root (no traversal, no
 * absolute paths) and against the protected list before any mutation.
 * Locally the root is the sibling checkout (~/Sites/default); in the
 * shared pod it's the app directory mounted next to the builder.
 */
class Workspace
{
    protected const BINARY_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'ico', 'webp', 'avif',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'zip', 'tar', 'gz', 'pdf', 'sqlite', 'exe',
    ];

    /**
     * @param  array<int, string>  $protected
     * @param  array<int, string>  $hidden
     * @param  array<int, string>  $commands
     */
    public function __construct(
        protected string $root,
        protected array $protected = [],
        protected array $hidden = [],
        protected array $commands = [],
        protected int $maxReadBytes = 262144,
    ) {
        $this->root = rtrim($root, '/');
    }

    public static function fromConfig(): self
    {
        $config = config('builder.workspace');

        return new self(
            $config['root'],
            $config['protected'] ?? [],
            $config['hidden'] ?? [],
            $config['commands'] ?? [],
            $config['max_read_bytes'] ?? 262144,
        );
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return array<int, string>
     */
    public function allowedCommands(): array
    {
        return $this->commands;
    }

    public function read(string $path): string
    {
        $path = $this->normalize($path);
        $this->guardRead($path);

        $full = $this->full($path);

        if (! is_file($full)) {
            throw new WorkspaceException("File not found: {$path}. Use list_files or search_files to locate it.");
        }

        if ($this->isBinary($path)) {
            throw new WorkspaceException("{$path} is a binary file and cannot be read as text.");
        }

        if (filesize($full) > $this->maxReadBytes) {
            throw new WorkspaceException("{$path} is larger than the read limit. Use search_files to find the relevant section.");
        }

        return (string) file_get_contents($full);
    }

    public function write(string $path, string $contents): void
    {
        $path = $this->normalize($path);
        $this->guardWrite($path);

        $full = $this->full($path);

        File::ensureDirectoryExists(dirname($full));
        File::put($full, $contents);
    }

    public function delete(string $path): void
    {
        $path = $this->normalize($path);
        $this->guardWrite($path);

        $full = $this->full($path);

        if (is_dir($full)) {
            throw new WorkspaceException("{$path} is a directory. Delete the files inside it individually.");
        }

        if (! is_file($full)) {
            throw new WorkspaceException("File not found: {$path}.");
        }

        File::delete($full);
    }

    /**
     * Replace an exact string within a file. The search string must match
     * exactly once unless $all is set — the same contract that makes
     * targeted edits reliable in agent loops.
     */
    public function replace(string $path, string $search, string $replace, bool $all = false): int
    {
        if ($search === '') {
            throw new WorkspaceException('The search string must not be empty.');
        }

        $path = $this->normalize($path);
        $this->guardWrite($path);

        $contents = $this->read($path);
        $count = substr_count($contents, $search);

        if ($count === 0) {
            throw new WorkspaceException("No exact match for the search string in {$path}. Read the file again and copy the text exactly, including whitespace.");
        }

        if ($count > 1 && ! $all) {
            throw new WorkspaceException("The search string matches {$count} places in {$path}. Include more surrounding context to make it unique, or pass replace_all.");
        }

        $this->write($path, str_replace($search, $replace, $contents));

        return $count;
    }

    public function tree(string $path = '', int $depth = 3): string
    {
        $path = $this->normalize($path);

        $full = $path === '' ? $this->root : $this->full($path);

        if (! is_dir($full)) {
            throw new WorkspaceException("Directory not found: {$path}.");
        }

        $lines = [];
        $this->walk($full, $path, $depth, $lines);

        return $lines === [] ? '(empty)' : implode("\n", $lines);
    }

    /**
     * Case-insensitive substring search across text files.
     */
    public function search(string $query, string $path = ''): string
    {
        if (trim($query) === '') {
            throw new WorkspaceException('The search query must not be empty.');
        }

        $path = $this->normalize($path);
        $base = $path === '' ? $this->root : $this->full($path);

        if (! is_dir($base)) {
            throw new WorkspaceException("Directory not found: {$path}.");
        }

        $finder = (new Finder)
            ->files()
            ->in($base)
            ->exclude($this->hidden)
            ->ignoreDotFiles(false)
            ->size('< 512K');

        $matches = [];

        foreach ($finder as $file) {
            $relative = ltrim(($path === '' ? '' : $path.'/').$file->getRelativePathname(), '/');

            if ($this->isBinary($relative) || $this->isHidden($relative) || Str::startsWith($relative, '.env')) {
                continue;
            }

            foreach (explode("\n", $file->getContents()) as $number => $line) {
                if (stripos($line, $query) !== false) {
                    $matches[] = $relative.':'.($number + 1).': '.trim(Str::limit($line, 200));

                    if (count($matches) >= 100) {
                        return implode("\n", $matches)."\n…(more matches truncated)";
                    }
                }
            }
        }

        return $matches === [] ? 'No matches found.' : implode("\n", $matches);
    }

    /**
     * Run one of the allow-listed commands inside the workspace.
     */
    public function run(string $command): string
    {
        $command = trim($command);

        if (! in_array($command, $this->commands, true)) {
            throw new WorkspaceException(
                "Command not allowed: {$command}. Allowed commands: ".implode(' | ', $this->commands)
            );
        }

        $result = Process::path($this->root)->timeout(180)->run($command);

        $output = trim($result->output()."\n".$result->errorOutput());

        return "exit code: {$result->exitCode()}\n".Str::limit($output, 8000, "\n…(output truncated)");
    }

    /**
     * Validate and canonicalize a workspace-relative path.
     */
    public function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim(preg_replace('#^\./#', '', $path) ?? '', '/');

        if (str_contains($path, "\0")) {
            throw new WorkspaceException('Invalid path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                throw new WorkspaceException('Paths must stay inside the app — ".." segments are not allowed.');
            }
        }

        return $path;
    }

    protected function full(string $normalizedPath): string
    {
        return $normalizedPath === '' ? $this->root : $this->root.'/'.$normalizedPath;
    }

    protected function guardRead(string $path): void
    {
        if ($path === '.git' || Str::startsWith($path, '.git/') || Str::startsWith($path, '.env')) {
            throw new WorkspaceException("{$path} is not readable from the builder.");
        }
    }

    protected function guardWrite(string $path): void
    {
        if ($path === '') {
            throw new WorkspaceException('A file path is required.');
        }

        foreach ($this->protected as $protected) {
            if ($path === $protected || Str::startsWith($path, $protected.'/')) {
                throw new WorkspaceException("{$path} is protected and cannot be modified from the builder.");
            }
        }
    }

    protected function isHidden(string $path): bool
    {
        foreach ($this->hidden as $hidden) {
            if ($path === $hidden || Str::startsWith($path, $hidden.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function isBinary(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::BINARY_EXTENSIONS, true);
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function walk(string $directory, string $relative, int $depth, array &$lines): void
    {
        if ($depth < 1 || count($lines) >= 500) {
            return;
        }

        $entries = scandir($directory) ?: [];
        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryRelative = ltrim($relative.'/'.$entry, '/');

            if ($this->isHidden($entryRelative)) {
                continue;
            }

            if (is_dir($directory.'/'.$entry)) {
                $lines[] = $entryRelative.'/';
                $this->walk($directory.'/'.$entry, $entryRelative, $depth - 1, $lines);
            } else {
                $lines[] = $entryRelative;
            }
        }
    }
}
