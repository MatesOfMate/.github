<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Validation;

use MatesOfMate\RectorExtension\Validation\PathValidator;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class PathValidatorTest extends TestCase
{
    public function testValidateAllowsRelativePathInsideProjectRoot(): void
    {
        $projectRoot = $this->createProject(['src/Foo.php' => '<?php']);

        $result = (new PathValidator($projectRoot))->validate('src/Foo.php');

        $this->assertSame('src/Foo.php', $result);
    }

    public function testValidateAllowsAbsolutePathInsideProjectRootAsRelativePath(): void
    {
        $projectRoot = $this->createProject(['src/Foo.php' => '<?php']);

        $result = (new PathValidator($projectRoot))->validate($projectRoot.'/src/Foo.php');

        $this->assertSame('src/Foo.php', $result);
    }

    public function testValidateRejectsPathOutsideProjectRoot(): void
    {
        $projectRoot = $this->createProject(['src/Foo.php' => '<?php']);
        $outside = tempnam(sys_get_temp_dir(), 'rector_outside_');
        $this->assertIsString($outside);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must be inside the project root');

        (new PathValidator($projectRoot))->validate($outside);
    }

    public function testValidateRejectsMissingPath(): void
    {
        $projectRoot = $this->createProject([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path does not exist');

        (new PathValidator($projectRoot))->validate('src/Missing.php');
    }

    /**
     * @param array<string, string> $files
     */
    private function createProject(array $files): string
    {
        $projectRoot = sys_get_temp_dir().'/rector_path_test_'.bin2hex(random_bytes(4));
        mkdir($projectRoot, 0777, true);

        foreach ($files as $file => $content) {
            $path = $projectRoot.'/'.$file;
            $directory = \dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $content);
        }

        $realPath = realpath($projectRoot);
        $this->assertIsString($realPath);

        return $realPath;
    }
}
