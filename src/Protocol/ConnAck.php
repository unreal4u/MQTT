<?php

declare(strict_types=1);

namespace unreal4u\MQTT\Protocol;

use unreal4u\MQTT\Exceptions\Connect\BadUsernameOrPassword;
use unreal4u\MQTT\Exceptions\Connect\GenericError;
use unreal4u\MQTT\Exceptions\Connect\IdentifierRejected;
use unreal4u\MQTT\Exceptions\Connect\NotAuthorized;
use unreal4u\MQTT\Exceptions\Connect\ServerUnavailable;
use unreal4u\MQTT\Exceptions\Connect\UnacceptableProtocolVersion;
use unreal4u\MQTT\Internals\ClientInterface;
use unreal4u\MQTT\Internals\ProtocolBase;
use unreal4u\MQTT\Internals\ReadableContent;
use unreal4u\MQTT\Internals\ReadableContentInterface;
use unreal4u\MQTT\Internals\WritableContentInterface;

use function ord;
use function sprintf;

/**
 * The CONNACK Packet is the packet sent by the Server in response to a CONNECT Packet received from a Client.
 */
final class ConnAck extends ProtocolBase implements ReadableContentInterface
{
    use ReadableContent;

    private const CONTROL_PACKET_VALUE = 2;

    /**
     * The connect return code. If a server sends a CONNACK packet containing a non-zero return code it MUST then close
     * the Network Connection
     *
     * @see http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html#_Table_3.1_-
     *
     * 0 = Connection accepted
     * 1 = Connection Refused, unacceptable protocol version
     * 2 = Connection Refused, identifier rejected
     * 3 = Connection Refused, Server unavailable
     * 4 = Connection Refused, bad user name or password
     * 5 = Connection Refused, not authorized
     * 6-255 = Reserved for future use
     * @var int
     */
    private $connectReturnCode = -1;

    /**
     * @inheritdoc
     *
     * @param string $rawMQTTHeaders
     * @param ClientInterface $client
     * @return ReadableContentInterface
     * @throws GenericError
     * @throws NotAuthorized
     * @throws BadUsernameOrPassword
     * @throws ServerUnavailable
     * @throws IdentifierRejected
     * @throws UnacceptableProtocolVersion
     */
    public function fillObject(string $rawMQTTHeaders, ClientInterface $client): ReadableContentInterface
    {
        $this->connectReturnCode = ord($rawMQTTHeaders[3]);
        if ($this->connectReturnCode !== 0) {
            // We have detected a problem while connecting to the broker, send out the correct exception
            $this->throwConnectException();
        }

        return $this;
    }

    /**
     * Don't know why you should ever need this, but don't allow to overwrite it
     * @return int
     */
    public function getConnectReturnCode(): int
    {
        return $this->connectReturnCode;
    }

    /**
     * Will throw an exception in case there is something bad with the connection
     *
     * @see http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/errata01/os/mqtt-v3.1.1-errata01-os-complete.html#_Toc385349257
     *
     * @return ConnAck
     * @throws GenericError
     * @throws NotAuthorized
     * @throws BadUsernameOrPassword
     * @throws ServerUnavailable
     * @throws IdentifierRejected
     * @throws UnacceptableProtocolVersion
     */
    private function throwConnectException(): self
    {
        switch ($this->connectReturnCode) {
            case 1:
                throw new UnacceptableProtocolVersion(
                    'The Server does not support the level of the MQTT protocol requested by the Client'
                );
            case 2:
                throw new IdentifierRejected('The Client identifier is correct UTF-8 but not allowed by the Server');
            case 3:
                throw new ServerUnavailable('The Network Connection has been made but the MQTT service is unavailable');
            case 4:
                throw new BadUsernameOrPassword('The data in the user name or password is malformed');
            case 5:
                throw new NotAuthorized('The Client is not authorized to connect');
            default:
                throw new GenericError(sprintf(
                    'Reserved for future use or error not implemented yet (Error code: %d)',
                    $this->connectReturnCode
                ));
        }
    }

    /**
     * @inheritdoc
     */
    public function performSpecialActions(ClientInterface $client, WritableContentInterface $originalRequest): bool
    {
        $client
            ->setConnected(true)
            ->updateLastCommunication();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getOriginControlPacket(): int
    {
        return Connect::getControlPacketValue();
    }
}
