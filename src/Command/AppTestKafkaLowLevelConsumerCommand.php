<?php

namespace App\Command;

use App\Exception\CommandRuntimeException;
use App\Exception\CommandUnexpectedValueException;
use RdKafka\Conf;
use RdKafka\Consumer;
use RdKafka\TopicConf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AppTestKafkaLowLevelConsumerCommand
 *
 * @package App\Command
 * @author davamigo@gmail.com
 */
class AppTestKafkaLowLevelConsumerCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'app:test-kafka-consumer-low';

    /** @var int */
    protected $messagesRetrieved = 0;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Test an Apache Kafka consumer (low level)')
            ->addOption('from-beginning', 'b', InputOption::VALUE_NONE, 'Start from the beginning')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Daemon mode on');
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
        $startFromBeginning = $input->getOption('from-beginning');
        $daemonMode = $input->getOption('daemon');
        $topic = 'events';

        $io = new SymfonyStyle($input, $output);
        $io->writeln('Start listening topic ' . $topic);

        try {
            $this->consume(
                $topic,
                'Kafka-events-consumer',
                'kafkatest_queue',
                $startFromBeginning,
                $daemonMode
            );
        } catch (CommandUnexpectedValueException $exc) {
            $io->comment($exc->getMessage());
            return -1;
        } catch (CommandRuntimeException $exc) {
            $io->error($exc->getMessage());
            return -2;
        }

        $io->success('Total messages retrieved: ' . $this->messagesRetrieved);
        return 0;
    }

    /**
     * @param string $topic
     * @param string $groupId
     * @param string $brokers
     * @param bool   $startFromBeginning
     * @param bool   $daemonMode
     * @throws CommandRuntimeException
     * @throws CommandUnexpectedValueException
     */
    public function consume($topic, $groupId, $brokers, $startFromBeginning, $daemonMode) : void
    {
        $kafkaConf = new Conf();
        $kafkaConf->set('group.id', $groupId);

        $kafkaTopicConf = new TopicConf();
        $kafkaTopicConf->set('auto.commit.interval.ms', 1e3);
        $kafkaTopicConf->set('offset.store.sync.interval.ms', 60e3);
        $kafkaTopicConf->set('offset.store.method', 'broker');
//        $kafkaTopicConf->set('offset.store.method', 'file');
//        $kafkaTopicConf->set('offset.store.path', 'var/cache/kafka');
        $kafkaConf->setDefaultTopicConf($kafkaTopicConf);

        $kafkaConsumer = new Consumer($kafkaConf);
        $kafkaConsumer->setLogLevel(LOG_DEBUG);
        $kafkaConsumer->addBrokers($brokers);

        $kafkaTopic = $kafkaConsumer->newTopic($topic);

        // The first argument is the partition to consume from.
        // The second argument is the offset at which to start consumption. Valid values
        // are: RD_KAFKA_OFFSET_BEGINNING, RD_KAFKA_OFFSET_END, RD_KAFKA_OFFSET_STORED.
        $kafkaTopic->consumeStart(0, $startFromBeginning ? RD_KAFKA_OFFSET_BEGINNING : RD_KAFKA_OFFSET_STORED);

        $this->messagesRetrieved = 0;
        while (true) {
            // The first argument is the partition (again).
            // The second argument is the timeout.
            $msg = $kafkaTopic->consume(0, 1000);
            if (!$msg) {
                if ($daemonMode) {
                    usleep(50);
                } else {
                    throw new CommandUnexpectedValueException('Empty message received from topic ' . $topic);
                }
            } elseif ($msg->err == RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                if (!$daemonMode) {
                    return;
                }
            } elseif ($msg->err) {
                throw new CommandRuntimeException($msg->errstr());
            } else {
                dump($msg->payload);
                ++$this->messagesRetrieved;
            }
        }
    }
}
