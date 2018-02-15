<?php

namespace App\Command;

use App\Exception\CommandRuntimeException;
use App\Exception\CommandUnexpectedValueException;
use App\Model\KafkaConfig;
use RdKafka\Conf;
use RdKafka\Exception;
use RdKafka\KafkaConsumer;
use RdKafka\TopicConf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class AppTestKafkaHighLevelConsumerCommand
 *
 * @package App\Command
 * @author davamigo@gmail.com
 */
class AppTestKafkaHighLevelConsumerCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'app:test-kafka-consumer-high';

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
            ->setDescription('Test an Apache Kafka consumer (high-level)')
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
        // Read command line options
        $daemonMode = $input->getOption('daemon');

        // Message to the user
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Start listening topic ' . KafkaConfig::TOPIC_NAME);

        // Start consuming events
        try {
            $this->consume(
                KafkaConfig::TOPIC_NAME,
                KafkaConfig::HIGH_LEVEL_GROUP_ID,
                KafkaConfig::BROKER_LIST,
                $daemonMode
            );
        } catch (CommandUnexpectedValueException $exc) {
            $io->comment($exc->getMessage());
            return -1;
        } catch (CommandRuntimeException $exc) {
            $io->error($exc->getMessage());
            return -2;
        }

        // Message to the user
        $io->success('Total messages retrieved: ' . $this->messagesRetrieved);

        // End command
        return 0;
    }

    /**
     * @param string $topic
     * @param string $groupId
     * @param string $brokers
     * @param bool   $daemonMode
     * @throws CommandRuntimeException
     * @throws CommandUnexpectedValueException
     */
    public function consume(string $topic, string $groupId, string $brokers, bool $daemonMode) : void
    {
        // Kafka configuration
        $kafkaConf = new Conf();
        $kafkaConf->set('group.id', $groupId);
        $kafkaConf->set('bootstrap.servers', $brokers);
        $kafkaConf->set('enable.auto.commit', 'false');
        $kafkaConf->set('auto.commit.interval.ms', 0);
//        $kafkaConf->set('enable.auto.offset.store', 'false');

        // Set a rebalance callback to log partition assignments (optional)
        $kafkaConf->setRebalanceCb(function (KafkaConsumer $kafka, $err, array $partitions = null) {
            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    echo 'Assigning ' . (empty($partitions) ? 0 : count($partitions)) . ' partitions...';
                    $kafka->assign($partitions);
                    echo ' done!' . PHP_EOL;
                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    echo 'Revoking ' . (empty($partitions) ? 0 : count($partitions)) . ' partitions...';
                    $kafka->assign(null);
                    echo ' done!' . PHP_EOL;
                    break;

                default:
                    throw new CommandRuntimeException($err);
            }
        });

        // Topic configuration
        $kafkaTopicConf = new TopicConf();
        $kafkaTopicConf->set('auto.offset.reset', 'earliest');
        $kafkaTopicConf->set('enable.auto.commit', 'false');
//        $kafkaTopicConf->set('offset.store.method', 'broker');
        $kafkaConf->setDefaultTopicConf($kafkaTopicConf);

        try {
            $kafkaConsumer = new KafkaConsumer($kafkaConf);
            $kafkaConsumer->subscribe([$topic]);

            $this->messagesRetrieved = 0;
            while (true) {
                // The first argument is the partition (again).
                // The second argument is the timeout.
                $msg = $kafkaConsumer->consume(1000);
                if (!$msg) {
                    if ($daemonMode) {
                        usleep(50);
                    } else {
                        throw new CommandUnexpectedValueException('Empty message received from topic ' . $topic);
                    }
                } else {
                    switch ($msg->err) {
                        case RD_KAFKA_RESP_ERR_NO_ERROR:
                            dump($msg->payload);
                            ++$this->messagesRetrieved;
                            $kafkaConsumer->commit($msg);
                            break;

                        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                            if (!$daemonMode) {
                                return;
                            }
                            break;

                        case RD_KAFKA_RESP_ERR__TIMED_OUT:
                            if (!$daemonMode) {
                                throw new CommandUnexpectedValueException('Timeout consuming topic ' . $topic);
                            }
                            break;

                        default:
                            throw new CommandRuntimeException($msg->errstr(), $msg->err);
                    }
                }
            }
        } catch (Exception $exc) {
            throw new CommandRuntimeException($exc);
        }
    }
}
