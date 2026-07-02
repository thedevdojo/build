<?php

use App\Ai\Workspace;
use App\Ai\WorkspaceException;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = storage_path('framework/testing/workspace-'.uniqid());

    File::ensureDirectoryExists($this->root.'/resources/views');
    File::put($this->root.'/routes.php', "<?php\n// routes\n");
    File::put($this->root.'/resources/views/home.blade.php', "<h1>Hello world</h1>\n<p>Welcome</p>\n");
    File::ensureDirectoryExists($this->root.'/vendor/acme');
    File::put($this->root.'/vendor/acme/lib.php', 'vendor code');
    File::put($this->root.'/.env', 'APP_KEY=secret');

    $this->workspace = new Workspace(
        $this->root,
        protected: ['.env', 'vendor', '.git'],
        hidden: ['vendor', '.git'],
        commands: ['echo allowed'],
    );
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

test('it reads and writes files, creating parent directories', function () {
    $this->workspace->write('resources/views/pages/pricing.blade.php', '<x-layouts.marketing>Pricing</x-layouts.marketing>');

    expect($this->workspace->read('resources/views/pages/pricing.blade.php'))->toContain('Pricing');
});

test('it rejects path traversal', function () {
    $this->workspace->write('../outside.txt', 'nope');
})->throws(WorkspaceException::class, '".." segments are not allowed');

test('it refuses to write protected paths', function (string $path) {
    $this->workspace->write($path, 'nope');
})->with(['.env', 'vendor/acme/lib.php'])->throws(WorkspaceException::class, 'protected');

test('it refuses to read env files', function () {
    $this->workspace->read('.env');
})->throws(WorkspaceException::class, 'not readable');

test('it replaces an exact unique match', function () {
    $count = $this->workspace->replace('resources/views/home.blade.php', 'Hello world', 'Hello DevDojo');

    expect($count)->toBe(1)
        ->and($this->workspace->read('resources/views/home.blade.php'))->toContain('Hello DevDojo');
});

test('it rejects an edit whose search string is missing', function () {
    $this->workspace->replace('resources/views/home.blade.php', 'Not in the file', 'x');
})->throws(WorkspaceException::class, 'No exact match');

test('it rejects an ambiguous edit unless replace_all is set', function () {
    File::put($this->root.'/resources/views/home.blade.php', "same\nsame\n");

    expect(fn () => $this->workspace->replace('resources/views/home.blade.php', 'same', 'changed'))
        ->toThrow(WorkspaceException::class, 'matches 2 places');

    expect($this->workspace->replace('resources/views/home.blade.php', 'same', 'changed', all: true))->toBe(2)
        ->and($this->workspace->read('resources/views/home.blade.php'))->toBe("changed\nchanged\n");
});

test('it deletes files but never directories', function () {
    $this->workspace->delete('routes.php');

    expect(file_exists($this->root.'/routes.php'))->toBeFalse()
        ->and(fn () => $this->workspace->delete('resources/views'))->toThrow(WorkspaceException::class, 'is a directory');
});

test('the tree hides noisy directories', function () {
    $tree = $this->workspace->tree();

    expect($tree)->toContain('resources/views/home.blade.php')
        ->and($tree)->toContain('routes.php')
        ->and($tree)->not->toContain('vendor');
});

test('search returns path, line, and text', function () {
    expect($this->workspace->search('welcome'))->toContain('resources/views/home.blade.php:2: <p>Welcome</p>')
        ->and($this->workspace->search('no-such-string'))->toBe('No matches found.');
});

test('only allow-listed commands can run', function () {
    expect($this->workspace->run('echo allowed'))->toContain('exit code: 0')
        ->and(fn () => $this->workspace->run('rm -rf /'))->toThrow(WorkspaceException::class, 'Command not allowed');
});
