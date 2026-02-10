<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Console;

use Aysnc\AI\LlmEval\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RunCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/llm-eval-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    public function testRunFailsForNonExistentFile(): void
    {
        $app = new Application();
        $command = $app->find('run');
        $tester = new CommandTester($command);

        $tester->execute(['file' => '/non/existent/file.php']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testRunFailsForInvalidEvaluationFile(): void
    {
        $app = new Application();
        $command = $app->find('run');
        $tester = new CommandTester($command);

        $filename = $this->tempDir . '/invalid.php';
        file_put_contents($filename, '<?php return "not an LlmEval";');

        $tester->execute(['file' => $filename]);

        $this->assertSame(1, $tester->getStatusCode());

        // Normalize whitespace since Symfony Console may wrap long lines
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('must return an LlmEval instance', $display);
    }

    public function testRunWithPassingEvaluation(): void
    {
        $app = new Application();
        $command = $app->find('run');
        $tester = new CommandTester($command);

        $filename = $this->createMockEvalFile(passing: true);

        $tester->execute(['file' => $filename]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('PASS', $tester->getDisplay());
        $this->assertStringContainsString('All evaluations passed', $tester->getDisplay());
    }

    public function testRunWithFailingEvaluation(): void
    {
        $app = new Application();
        $command = $app->find('run');
        $tester = new CommandTester($command);

        $filename = $this->createMockEvalFile(passing: false);

        $tester->execute(['file' => $filename]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('FAIL', $tester->getDisplay());
        $this->assertStringContainsString('failed', $tester->getDisplay());
    }

    public function testRunWithJsonFormat(): void
    {
        $app = new Application();
        $command = $app->find('run');
        $tester = new CommandTester($command);

        $filename = $this->createMockEvalFile(passing: true);

        $tester->execute(['file' => $filename, '--format' => 'json']);

        $this->assertSame(0, $tester->getStatusCode());

        // Output should be valid JSON
        $output = $tester->getDisplay();
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $json = substr($output, $jsonStart);
            $data = json_decode($json, true);
            $this->assertIsArray($data);
            $this->assertArrayHasKey('passed', $data);
            $this->assertArrayHasKey('results', $data);
        }
    }

    /**
     * Create a mock evaluation file that uses a mock provider.
     */
    private function createMockEvalFile(bool $passing): string
    {
        $expected = $passing ? 'test response' : 'wrong';

        $content = <<<PHP
<?php

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;

// Create a mock provider
\$provider = new class implements ProviderInterface {
    public function complete(string \$prompt, array \$options = []): Response
    {
        return new Response(text: 'test response', model: 'mock');
    }
};

\$dataset = Dataset::fromArray([
    ['prompt' => 'test', 'expected' => '{$expected}'],
]);

return LlmEval::create('test-eval')
    ->provider(\$provider)
    ->dataset(\$dataset)
    ->assertions(function (\$expect, \$testCase): void {
        \$expected = \$testCase->getExpected();
        if (\$expected !== null) {
            \$expect->contains(\$expected);
        }
    });
PHP;

        $filename = $this->tempDir . '/eval-' . ($passing ? 'pass' : 'fail') . '.php';
        file_put_contents($filename, $content);

        return $filename;
    }
}
