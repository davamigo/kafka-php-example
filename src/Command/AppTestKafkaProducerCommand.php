<?php

namespace App\Command;

use App\Model\KafkaConfig;
use RdKafka\Producer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AppTestKafkaProducerCommand
 *
 * @package App\Command
 * @author davamigo@gmail.com
 */
class AppTestKafkaProducerCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'app:test-kafka-producer';

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Test an Apache Kafka producer.')
            ->addArgument('count', InputArgument::OPTIONAL, 'Number of messages', 1);
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Read command line options
        $count = $input->getArgument('count');

        // Message to the user
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Publishing ' . $count . ' messges to topic "' . KafkaConfig::TOPIC_NAME . '"...');

        // Create the Kafka producer
        $kafkaProducer = new Producer();
        $kafkaProducer->setLogLevel(LOG_DEBUG);
        $kafkaProducer->addBrokers(KafkaConfig::BROKER_LIST);

        // Create the Kafka topic
        $kafkaTopic = $kafkaProducer->newTopic(KafkaConfig::TOPIC_NAME);

        // Loop
        $i = 0;
        do {
            $key = $this->generateRandomKey();
            $message = $this->buildMessage($key);

            // Send the message to Kafka
            $kafkaTopic->produce(
                RD_KAFKA_PARTITION_UA,
                0,
                $message,
                $key
            );

            // Wait
            $kafkaProducer->poll(0);
        } while (++$i < $count);

        // Wait untill all messages are confirmed
        while ($kafkaProducer->getOutQLen() > 0) {
            $kafkaProducer->poll(50);
        }

        // Message to the user
        $io->success('Produced ' . $count . ' messages.');

        // End command
        return 0;
    }

    /**
     * Generate the next key to use which is a random key
     *
     * @return int
     */
    private function generateRandomKey() : int
    {
        return rand(0, 99);
    }

    /**
     * Builds the message to sent to Kafka
     *
     * @param int $key
     * @return string
     */
    private function buildMessage(int $key) : string
    {
        return json_encode([
            'type' => 'event',
            'name' => 'TestKafKaPRoducer',
            'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
            'payload' => [
                'data' => $this->nextProducerData()
            ],
            'metadata' => [
                'key' => $key,
                'topic' => KafkaConfig::TOPIC_NAME
            ]
        ]);
    }

    /**
     * Return the next producer data which is a sequential number
     *
     * @return int
     */
    private function nextProducerData() : int
    {
        $data = 0;
        $buffer = @file_get_contents(KafkaConfig::PRODUCER_DATA_FILE);
        if (false !== $buffer) {
            $data = intval($buffer);
        }
        @file_put_contents(KafkaConfig::PRODUCER_DATA_FILE, sprintf("%d", 1 + $data));
        return $data;
    }
}
