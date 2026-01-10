<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Console;

use Aysnc\AI\LlmEval\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/llm-eval-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $files = glob($this->tempDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    public function testInitCreatesEvaluationFile(): void
    {
        $app = new Application();
        $command = $app->find('init');
        $tester = new CommandTester($command);

        $filename = $this->tempDir . '/eval.php';

        $tester->execute(['filename' => $filename]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($filename);
        $this->assertStringContainsString('Created evaluation file', $tester->getDisplay());
    }

    public function testInitWithCustomFilename(): void
    {
        $app = new Application();
        $command = $app->find('init');
        $tester = new CommandTester($command);

        $filename = $this->tempDir . '/my-custom-eval.php';

        $tester->execute(['filename' => $filename]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($filename);
    }

    public function testInitFailsIfFileExists(): void
    {
        $app = new Application();
        $command = $app->find('init');
        $tester = new CommandTester($command);

        $filename = $this->tempDir . '/existing.php';
        file_put_contents($filename, '<?php // existing');

        $tester->execute(['filename' => $filename]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File already exists', $tester->getDisplay());
    }

    public function testInitCreatesValidPhpFile(): void
    {
        $app = new Application();
        $command = $app->find('init');
        $tester = new CommandTester($command);

        $filename = $this->tempDir . '/eval.php';

        $tester->execute(['filename' => $filename]);

        $content = file_get_contents($filename);
        $this->assertIsString($content);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('LlmEval::create', $content);
        $this->assertStringContainsString('AnthropicProvider', $content);
    }
}
