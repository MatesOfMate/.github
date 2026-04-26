<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Adapter;

use MatesOfMate\Benchmark\Adapter\AdapterRegistry;
use MatesOfMate\Benchmark\Adapter\AssistantAdapterInterface;
use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Adapter\Exception\UnsupportedAdapterException;
use MatesOfMate\Benchmark\Adapter\NullAdapter;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class AdapterRegistryTest extends TestCase
{
    public function testRegistersAdaptersFromConstructor(): void
    {
        $registry = new AdapterRegistry([new NullAdapter()]);

        $this->assertTrue($registry->has('null'));
        $this->assertSame(['null'], $registry->names());
        $this->assertInstanceOf(NullAdapter::class, $registry->get('null'));
    }

    public function testRegisterAddsAdapterAfterConstruction(): void
    {
        $registry = new AdapterRegistry();
        $registry->register($this->makeAdapter('codex'));
        $registry->register($this->makeAdapter('claude'));

        $this->assertSame(['claude', 'codex'], $registry->names());
    }

    public function testGetThrowsForUnknownAdapterWithAvailableList(): void
    {
        $registry = new AdapterRegistry([new NullAdapter()]);

        try {
            $registry->get('codex');
            $this->fail('Expected UnsupportedAdapterException.');
        } catch (UnsupportedAdapterException $exception) {
            $this->assertStringContainsString('"codex"', $exception->getMessage());
            $this->assertStringContainsString('null', $exception->getMessage());
        }
    }

    public function testGetThrowsWhenRegistryIsEmpty(): void
    {
        $registry = new AdapterRegistry();

        $this->expectException(UnsupportedAdapterException::class);
        $registry->get('null');
    }

    private function makeAdapter(string $name): AssistantAdapterInterface
    {
        return new class($name) implements AssistantAdapterInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function run(AssistantRunInput $input): AssistantRunResult
            {
                return AssistantRunResult::success(stdout: '', durationMs: 0.0);
            }
        };
    }
}
