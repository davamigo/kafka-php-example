<?php

namespace App\Model;

/**
 * Class KafkaConfig
 *
 * @package App\Model
 * @author davamigo@gmail.com
 */
class KafkaConfig
{
    /** @var String */
    const BROKER_LIST = 'kafkatest_queue';

    /** @var String */
    const TOPIC_NAME = 'events';

    /** @var String */
    const HIGH_LEVEL_GROUP_ID = 'Kafka-events-high-level-consumer';

    /** @var String */
    const PRODUCER_DATA_FILE = 'var/cache/kafka/producer.dat';
}
