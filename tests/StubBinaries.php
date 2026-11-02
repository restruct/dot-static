<?php

namespace Restruct\Dot\Tests;

/**
 * Creates throwaway shell scripts that stand in for dot, so error handling can be tested
 * without depending on how a real Graphviz build fails.
 */
trait StubBinaries
{
    /** @var list<string> */
    private array $stubFiles = [];

    /**
     * Write an executable /bin/sh script and return its path.
     */
    private function stub(string $body, bool $executable = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dotstub');
        file_put_contents($path, "#!/bin/sh\n" . $body . "\n");
        chmod($path, $executable ? 0755 : 0644);
        $this->stubFiles[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->stubFiles as $file) {
            @unlink($file);
        }
        $this->stubFiles = [];
        parent::tearDown();
    }
}
