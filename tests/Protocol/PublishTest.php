<?php

declare(strict_types=1);

namespace tests\unreal4u\MQTT;

use PHPUnit\Framework\TestCase;
use tests\unreal4u\MQTT\Mocks\ClientMock;
use unreal4u\MQTT\Application\EmptyReadableResponse;
use unreal4u\MQTT\DataTypes\Message;
use unreal4u\MQTT\DataTypes\TopicName;
use unreal4u\MQTT\DataTypes\PacketIdentifier;
use unreal4u\MQTT\DataTypes\QoSLevel;
use unreal4u\MQTT\Exceptions\InvalidRequest;
use unreal4u\MQTT\Protocol\PingReq;
use unreal4u\MQTT\Protocol\PubAck;
use unreal4u\MQTT\Protocol\Publish;
use unreal4u\MQTT\Protocol\PubRec;

class PublishTest extends TestCase
{
    /**
     * @var Publish
     */
    private $publish;

    /**
     * @var Message
     */
    private $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publish = new Publish();
        $this->message = new Message('Hello test world!', new TopicName('t'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->publish = null;
    }

    public function testGetOriginControlPacket(): void
    {
        $this->assertSame(0, $this->publish->getOriginControlPacket());
    }

    public function testThrowExceptionNoMessageProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publish->createVariableHeader();
    }

    public function testPublishBasicMessage(): void
    {
        $this->publish->setMessage($this->message);
        $variableHeader = $this->publish->createVariableHeader();
        $this->assertSame('AAF0', base64_encode($variableHeader));
    }

    public function testPublishComplexMessage(): void
    {
        $this->message->setQoSLevel(new QoSLevel(1));
        $this->message->setRetainFlag(true);

        $this->publish->setMessage($this->message);
        $this->publish->setPacketIdentifier(new PacketIdentifier(1));
        $variableHeader = $this->publish->createVariableHeader();
        $this->assertSame('AAF0AAE=', base64_encode($variableHeader));
    }

    public function testNoAnswerRequired(): void
    {
        $this->publish->setMessage($this->message);
        $this->assertFalse($this->publish->shouldExpectAnswer());
    }

    public function testAnswerRequired(): void
    {
        $this->message->setQoSLevel(new QoSLevel(1));
        $this->publish->setMessage($this->message);
        $this->assertTrue($this->publish->shouldExpectAnswer());
    }

    public function testEmptyExpectedAnswer(): void
    {
        $this->publish->setMessage($this->message);
        $answer = $this->publish->expectAnswer('000', new ClientMock());
        $this->assertInstanceOf(EmptyReadableResponse::class, $answer);
    }

    public function testQoSLevel1ExpectedAnswer(): void
    {
        $this->message->setQoSLevel(new QoSLevel(1));
        $this->publish->setMessage($this->message);
        $this->publish->setPacketIdentifier(new PacketIdentifier(1));
        $this->publish->createVariableHeader();
        /** @var PubAck $answer */
        $answer = $this->publish->expectAnswer(base64_decode('QAIAAQ=='), new ClientMock());
        $this->assertInstanceOf(PubAck::class, $answer);
        $this->assertSame($answer->getPacketIdentifier(), $this->publish->getPacketIdentifier());
    }

    public function testQoSLevel2ExpectedAnswer(): void
    {
        $this->message->setQoSLevel(new QoSLevel(2));
        $this->publish->setMessage($this->message);
        $this->publish->setPacketIdentifier(new PacketIdentifier(111));
        $this->publish->createVariableHeader();
        /** @var PubAck $answer */
        $answer = $this->publish->expectAnswer(base64_decode('UAIAbw=='), new ClientMock());
        $this->assertInstanceOf(PubRec::class, $answer);
        $this->assertSame($answer->getPacketIdentifier(), $this->publish->getPacketIdentifier());
    }

    public function testNoPayloadException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publish->createPayload();
    }

    public function testGoodPayload(): void
    {
        $this->publish->setMessage($this->message);
        $this->assertSame('Hello test world!', $this->publish->createPayload());
    }

    public function testGetMessage(): void
    {
        $this->publish->setMessage($this->message);
        $objectMessage = $this->publish->getMessage();
        $this->assertSame($this->message, $objectMessage);
    }

    public function providerCalculateIncomingQoSLevel(): array
    {
        $mapValues[] = [48, 0];
        $mapValues[] = [50, 1];
        $mapValues[] = [58, 1]; // Redelivery of QoS level 1 type message
        $mapValues[] = [52, 2];

        return $mapValues;
    }

    /**
     * @dataProvider providerCalculateIncomingQoSLevel
     * @param int $bitString
     * @param int $expectedQoS
     * @throws \ReflectionException
     */
    public function testCalculateIncomingQoSLevel(int $bitString, int $expectedQoS): void
    {
        $method = new \ReflectionMethod(Publish::class, 'determineIncomingQoSLevel');
        $method->setAccessible(true);

        $qosLevel = $method->invoke(new Publish(), $bitString);
        $this->assertSame($expectedQoS, $qosLevel->getQoSLevel());
    }

    public function providerAnalyzeFirstByte(): array
    {
        $mapValues[] = [48, new QoSLevel(0), false, false];
        $mapValues[] = [50, new QoSLevel(1), false, false];
        $mapValues[] = [51, new QoSLevel(1), true, false];
        $mapValues[] = [58, new QoSLevel(1), false, true];
        $mapValues[] = [59, new QoSLevel(1), true, true];
        $mapValues[] = [52, new QoSLevel(2), false, false];
        $mapValues[] = [53, new QoSLevel(2), true, false];

        return $mapValues;
    }

    /**
     * @dataProvider providerAnalyzeFirstByte
     * @param int $firstByte
     * @param QoSLevel $qoSLevel
     * @param bool $isRetained
     * @param bool $isRedelivery
     * @throws \ReflectionException
     */
    public function testAnalyzeFirstByte(int $firstByte, QoSLevel $qoSLevel, bool $isRetained, bool $isRedelivery): void
    {
        $method = new \ReflectionMethod(Publish::class, 'analyzeFirstByte');
        $method->setAccessible(true);

        $this->publish->setMessage($this->message);
        $publishObject = $method->invoke($this->publish, $firstByte, $qoSLevel);
        $this->assertSame($isRetained, $publishObject->getMessage()->isRetained());
        $this->assertSame($isRedelivery, $publishObject->isRedelivery);
    }

    public function providerPerformSpecialActions(): array
    {
        $mapValues[] = [0, 126, ''];
        $mapValues[] = [1, 127, PubAck::class];
        $mapValues[] = [2, 128, PubRec::class];

        return $mapValues;
    }

    /**
     * @dataProvider providerPerformSpecialActions
     * @param int $QoSLevel
     * @param int $packetIdentifier
     * @param string $expectedClassType
     */
    public function testPerformSpecialActions(int $QoSLevel, int $packetIdentifier, string $expectedClassType): void
    {
        $clientMock = new ClientMock();
        $this->message->setQoSLevel(new QoSLevel($QoSLevel));
        // Emulate an incoming message
        $this->publish->setMessage($this->message);
        $this->publish->setPacketIdentifier(new PacketIdentifier($packetIdentifier));

        $result = $this->publish->performSpecialActions($clientMock, new PingReq());
        $this->assertTrue($result);
        $this->assertSame($expectedClassType, $clientMock->processObjectWasCalledWithObjectType());
    }

    /**
     * @throws \ReflectionException
     */
    public function testComposePubRecAnswer(): void
    {
        $this->publish->setPacketIdentifier(new PacketIdentifier(123));
        $method = new \ReflectionMethod(Publish::class, 'composePubRecAnswer');
        $method->setAccessible(true);

        /** @var PubRec $pubRec */
        $pubRec = $method->invoke($this->publish);
        $this->assertInstanceOf(PubRec::class, $pubRec);
        $this->assertSame(123, $pubRec->getPacketIdentifier());
    }

    /**
     * @throws \ReflectionException
     */
    public function testComposePubAckAnswer(): void
    {
        $this->publish->setPacketIdentifier(new PacketIdentifier(124));
        $method = new \ReflectionMethod(Publish::class, 'composePubAckAnswer');
        $method->setAccessible(true);

        /** @var PubRec $pubRec */
        $pubAck = $method->invoke($this->publish);
        $this->assertInstanceOf(PubAck::class, $pubAck);
        $this->assertSame(124, $pubAck->getPacketIdentifier());
    }

    /**
     * @throws \ReflectionException
     */
    public function testCheckForValidPacketIdentifier(): void
    {
        $method = new \ReflectionMethod(Publish::class, 'checkForValidPacketIdentifier');
        $method->setAccessible(true);

        $this->expectException(InvalidRequest::class);
        $method->invoke($this->publish);
    }

    public function providerCompletePossibleIncompleteMessage(): array
    {
        // First case: 1 byte and the rest of the message
        $mapValues[] = ['MA==', 'FAAJZmlyc3RUZXN05rGJQeWtl0JD', 'MBQACWZpcnN0VGVzdOaxiUHlrZdCQw=='];
        // Second case: 4 bytes already in rawHeaders, the rest still to be provided
        $mapValues[] = ['MBQACQ==', 'Zmlyc3RUZXN05rGJQeWtl0JD', 'MBQACWZpcnN0VGVzdOaxiUHlrZdCQw=='];
        // Edge-case: all but 1 byte already in rawHeaders
        $mapValues[] = ['MBQACWZpcnN0VGVzdOaxiUHlrZdC', 'Qw==', 'MBQACWZpcnN0VGVzdOaxiUHlrZdCQw=='];
        // Edge-case: no bytes left
        $mapValues[] = ['MBQACWZpcnN0VGVzdOaxiUHlrZdCQw==', '', 'MBQACWZpcnN0VGVzdOaxiUHlrZdCQw=='];

        return $mapValues;
    }

    /**
     * @dataProvider providerCompletePossibleIncompleteMessage
     * @param string $firstBytes
     * @param string $append
     * @param string $expectedOutput
     * @throws \ReflectionException
     */
    public function testCompletePossibleIncompleteMessage(
        string $firstBytes,
        string $append,
        string $expectedOutput
    ): void {
        $method = new \ReflectionMethod(Publish::class, 'completePossibleIncompleteMessage');
        $method->setAccessible(true);

        $clientMock = new ClientMock();
        $clientMock->returnSpecificBrokerData([$append]);
        $output = base64_encode($method->invoke($this->publish, base64_decode($firstBytes), $clientMock));

        $this->assertSame($expectedOutput, $output);
        // If there is no more data to read, readBrokerData should never even be called
        $this->assertSame($append !== '', $clientMock->readBrokerDataWasCalled());
    }

    public function providerFillObject(): array
    {
        // QoS 0 with UTF-8 characters
        $mapVal[] = ['MBQACWZpcnN0VGVzdOaxiUHlrZdCQw==', 0, 'firstTest', '汉A字BC', 0];
        // QoS 1 with packetIdentifier 10
        $mapVal[] = ['MiIACWZpcnN0VGVzdAAKSGVsbG8gd29ybGQhISAoMSAvIDMp', 1, 'firstTest', 'Hello world!! (1 / 3)', 10];
        // QoS 1 with packetIdentifier 15 and different message
        $mapVal[] = ['MiIACWZpcnN0VGVzdAAPSGVsbG8gd29ybGQhISAoMyAvIDMp', 1, 'firstTest', 'Hello world!! (3 / 3)', 15];
        // QoS 2 with packetIdentifier 16
        $mapVal[] = ['NCIACWZpcnN0VGVzdAAQSGVsbG8gd29ybGQhISAoMSAvIDEp', 2, 'firstTest', 'Hello world!! (1 / 1)', 16];

        // Real-life example of message that did go wrong, ensure it never happens again
        $mapVal[] = [
            'Me0BABpzaW5nbGVsYW1wL3RlbGVtZXRyeS9TVEFURXsiVGltZSI6IjIwMTktMDMtMjZUMjI6MzM6MzciLCJVcHRpbWUiOiI0NVQwNzow' .
            'OTowMiIsIlZjYyI6My40MjMsIlNsZWVwTW9kZSI6IkR5bmFtaWMiLCJTbGVlcCI6NTAsIkxvYWRBdmciOjE5LCJQT1dFUiI6Ik9GRiIs' .
            'IldpZmkiOnsiQVAiOjEsIlNTSWQiOiJYWFhYWFhYWCIsIkJTU0lkIjoiQUE6QUE6QUE6QUE6QUE6QUEiLCJDaGFubmVsIjozLCJSU1NJ' .
            'Ijo2MH0=',
            0,
            'singlelamp/telemetry/STATE',
            '{"Time":"2019-03-26T22:33:37","Uptime":"45T07:09:02","Vcc":3.423,"SleepMode":"Dynamic","Sleep":50,"LoadA' .
            'vg":19,"POWER":"OFF","Wifi":{"AP":1,"SSId":"XXXXXXXX","BSSId":"AA:AA:AA:AA:AA:AA","Channel":3,"RSSI":60}',
            0
        ];

        return $mapVal;
    }

    /**
     * @dataProvider providerFillObject
     * @param string $rawData
     * @param int $expectedQoSLevel
     * @param string $expectedTopicName
     * @param string $expectedMessageContent
     * @param int|null $expectedPacketIdentifier
     */
    public function testFillObject(
        string $rawData,
        int $expectedQoSLevel,
        string $expectedTopicName,
        string $expectedMessageContent,
        int $expectedPacketIdentifier
    ): void {
        $this->publish->fillObject(base64_decode($rawData), new ClientMock());
        $message = $this->publish->getMessage();
        $this->assertSame($expectedQoSLevel, $message->getQoSLevel());
        $this->assertSame($expectedTopicName, $message->getTopicName());
        $this->assertSame($expectedMessageContent, $message->getPayload());
        if ($expectedPacketIdentifier !== 0) {
            $this->assertSame($expectedPacketIdentifier, $this->publish->getPacketIdentifier());
        }
    }
}
