# Kafka and Kafka Streams Proofs of Concept

## kafka-poc/kafka-examples-php

Basic examples using Kafka, PHP and Symfony4.

### Console commands

* `app:test-kafka-producer`:
    * Basic producer example.
    * Produces n messages (default=1).

* `app:test-kafka-consumer-high`:
    * Basic high-level consumer example.
    * Consumes all the messages in the topic.
    * Can be configured as a daemon [-d].

* `app:test-kafka-consumer-low`:
    * Basic low-level consumer example.
    * Can start reading from the beginning of the topic [-b].
    * Can be configured as a daemon [-d].

### Configuration

* brokers: **kafkatest_queue:9092**
* topic: **events**
* group id: **Kafka-events-high-level-consumer**

### Docker usage

* `docker/build.sh` to build/rebuild the images.
* `docker/start.sh` to start the containers.
* `docker/composer.sh install` to download the PHP dependencies (required first time).
* `docker/console.sh <command>` to run the console commands.
* `docker/logs.sh` to see the logs from the running containers.
* `docker/bash.sh` to enter in a bash session in the running Apache+PHP container.
* `docker/kafkabash.sh` to enter in a bash session in the running Kafka container. 
* `docker/stop.sh` to stop and remove the containers.
