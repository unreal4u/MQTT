<?php

declare(strict_types=1);

namespace unreal4u\MQTT\Protocol;

use OutOfRangeException;
use unreal4u\MQTT\Internals\ClientInterface;
use unreal4u\MQTT\Internals\PacketIdentifierFunctionality;
use unreal4u\MQTT\Internals\ProtocolBase;
use unreal4u\MQTT\Internals\ReadableContent;
use unreal4u\MQTT\Internals\ReadableContentInterface;
use unreal4u\MQTT\Internals\WritableContentInterface;

use function strlen;

/**
 * The UNSUBACK Packet is sent by the Server to the Client to confirm receipt of an UNSUBSCRIBE Packet.
 */
final class UnsubAck extends ProtocolBase implements ReadableContentInterface
{
    use ReadableContent;
    use PacketIdentifierFunctionality;

    private const CONTROL_PACKET_VALUE = 11;

    /**
     * @param string $rawMQTTHeaders
     * @param ClientInterface $client
     * @return ReadableContentInterface
     * @throws OutOfRangeException
     */
    public function fillObject(string $rawMQTTHeaders, ClientInterface $client): ReadableContentInterface
    {
        // Read the rest of the request out should only 1 byte have come in
        if (strlen($rawMQTTHeaders) === 1) {
            $rawMQTTHeaders .= $client->readBrokerData(3);
        }

        $this->setPacketIdentifierFromRawHeaders($rawMQTTHeaders);
        return $this;
    }

    /**
     * @inheritdoc
     * @throws \LogicException
     */
    public function performSpecialActions(ClientInterface $client, WritableContentInterface $originalRequest): bool
    {
        $this->controlPacketIdentifiers($originalRequest);
        $client->updateLastCommunication();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getOriginControlPacket(): int
    {
        return Unsubscribe::getControlPacketValue();
    }
}
