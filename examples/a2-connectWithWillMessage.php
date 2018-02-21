<?php

/**
 * This example will connect to the broker setting a will message while doing so
 */
declare(strict_types = 1);

use unreal4u\MQTT\Application\Topic;
use unreal4u\MQTT\Client;
use unreal4u\MQTT\Protocol\Connect;
use unreal4u\MQTT\Protocol\Connect\Parameters;
use unreal4u\MQTT\Application\Message;

include __DIR__ . '/00.basics.php';

// Create a new Message object
$willMessage = new Message();
// Set the payload
$willMessage->setPayload('If I die unexpectedly, please print this message');
// And set the topic
$willMessage->setTopic(new Topic('client/errors'));

// Now we will setup a new Connect Parameters object
$parameters = new Parameters('uniqueClientId123');
// Set the will message to the above created message
$parameters->setWill($willMessage);

// Setup the connection
$connect = new Connect();
// And set the parameters
$connect->setConnectionParameters($parameters);
// Example of invalid protocol which will throw an exception:
#$connect->protocolLevel = '0.0.1';
/** @var \unreal4u\MQTT\Protocol\ConnAck $connAck */

// Create a client connection
$client = new Client();
// And send the data
$connAck = $client->processObject($connect);

/*
 * If you subscribe to the above topic, a will message will be set when this exception below is thrown
 *
 * Example of subscription:
 * mosquitto_sub -v -t client/errors
 *
 * Where:
 * -t is topic
 * -v is verbose (will print out the topic before the message itself)
 */
for ($i = 0; $i < 3; $i++) {
    sleep(1);
    if ($i === 2) {
        throw new \LogicException('Throwing an exception unexpectedly will not trigger the destructor');
    }
}
