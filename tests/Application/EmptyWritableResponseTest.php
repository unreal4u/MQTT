<?php

declare(strict_types=1);

namespace tests\unreal4u\MQTT\Application;

use PHPUnit\Framework\TestCase;
use unreal4u\MQTT\Application\EmptyWritableResponse;

class EmptyWritableResponseTest extends TestCase
{
    /**
     * @var EmptyWritableResponse
     */
    private $emptyWritableResponse;

    protected function setUp(): void
    {
        $this->emptyWritableResponse = new EmptyWritableResponse();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->emptyWritableResponse = null;
    }

    public function testEmptyVariableHeader(): void
    {
        $this->assertSame('', $this->emptyWritableResponse->createVariableHeader());
    }

    public function testEmptyPayload(): void
    {
        $this->assertSame('', $this->emptyWritableResponse->createPayload());
    }

    public function testShouldExpectAnswer(): void
    {
        $this->assertFalse($this->emptyWritableResponse->shouldExpectAnswer());
    }
}
