<?php

declare(strict_types=1);

namespace tests\unreal4u\MQTT\Application;

use PHPUnit\Framework\TestCase;
use unreal4u\MQTT\Application\Topic;

class TopicTest extends TestCase
{
    public function test_emptyTopicName()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Topic('');
    }

    public function test_tooBigTopicName()
    {
        $this->expectException(\OutOfBoundsException::class);
        new Topic(str_repeat('-', 65537));
    }

    public function provider_validTopicNames(): array
    {
        $mapValues[] = ['First-topic-name'];
        $mapValues[] = ['𠜎𠜱𠝹𠱓'];
        $mapValues[] = ['Föllinge'];
        $mapValues[] = ['/Föllinge/First-topic-name/𠜎𠜱𠝹𠱓/normal'];

        return $mapValues;
    }

    /**
     * @dataProvider provider_validTopicNames
     * @param string $topic
     */
    public function test_validTopicNames(string $topic)
    {
        $topicName = new Topic($topic);
        $this->assertSame($topic, $topicName->getTopicName());
    }
}