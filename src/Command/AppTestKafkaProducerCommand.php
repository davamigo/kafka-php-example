<?php

namespace App\Command;

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

    /** @var string */
    protected static $offsetFile = 'var/cache/kafka/producer.offset';

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
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $count = $input->getArgument('count');

        $offset = 0;
        $buffer = @file_get_contents(self::$offsetFile);
        if (false !== $buffer) {
            $offset = intval($buffer);
        }

        $io = new SymfonyStyle($input, $output);

        $kafkaProducer = new Producer();
        $kafkaProducer->setLogLevel(LOG_DEBUG);
        $kafkaProducer->addBrokers('kafkatest_queue');

        $kafkaTopic = $kafkaProducer->newTopic('events');

        $i = 0;
        do {
            $message = [
                'type' => 'event',
                'name' => 'TestKafKaPRoducer',
                'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
                'payload' => [
                    'num' => ++$offset
                ]
            ];

            $kafkaTopic->produce(
                RD_KAFKA_PARTITION_UA,
                0,
                json_encode($message)
            );

            file_put_contents(self::$offsetFile, sprintf("%d", $offset));

            $kafkaProducer->poll(0);
        } while (++$i < $count);

        while ($kafkaProducer->getOutQLen() > 0) {
            $kafkaProducer->poll(50);
        }

        $io->success('Produced ' . $count . ' messages.');
        return 0;
    }
}
