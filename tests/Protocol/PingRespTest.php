<?php

declare(strict_types=1);

namespace tests\unreal4u\MQTT;

use PHPUnit\Framework\TestCase;
use tests\unreal4u\MQTT\Mocks\ClientMock;
use unreal4u\MQTT\Protocol\PingReq;
use unreal4u\MQTT\Protocol\PingResp;

class PingRespTest extends TestCase
{
    /**
     * @var PingResp
     */
    private $pingResp;

    protected function setUp(): void
    {
        $this->pingResp = new PingResp();
        parent::setUp();
    }

    public function testCheckControlPacketValue(): void
    {
        $success = \chr(208) . \chr(0);
        $this->pingResp->instantiateObject($success, new ClientMock());

        // If nothing went wrong above and we are still here, the test is a pass
        $this->assertTrue(true);
    }

    public function testGetOriginControlPacket(): void
    {
        $this->assertSame(PingReq::getControlPacketValue(), $this->pingResp->getOriginControlPacket());
    }

    public function testPerformSpecialActions(): void
    {
        $clientMock = new ClientMock();

        $this->pingResp->performSpecialActions($clientMock, new PingReq());
        $this->assertTrue($clientMock->updateLastCommunicationWasCalled());
    }
}
