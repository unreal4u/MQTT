<?php

declare(strict_types=1);

use unreal4u\MQTT\Application\Topic;
use unreal4u\MQTT\Client;
use unreal4u\MQTT\Protocol\Connect;
use unreal4u\MQTT\Protocol\Connect\Parameters;
use unreal4u\MQTT\Protocol\Subscribe;

include __DIR__ . '/00.basics.php';

$connect = new Connect();
$connect->setConnectionParameters(new Parameters('SubscribeOneTopic'));

$client = new Client();
$client->sendData($connect);

$subscribe = new Subscribe();
$subscribe->addTopics(new Topic(COMMON_TOPICNAME));
$subscribe->setPacketIdentifier(400);
foreach ($subscribe->loop($client) as $message) {
    printf(
        '%s-- Payload detected on topic "%s": %s + %s%s',
        PHP_EOL,
        $message->getTopicName(),
        PHP_EOL,
        $message->getPayload(),
        PHP_EOL
    );
}
