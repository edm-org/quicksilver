<?php

require_once '../Quicksilver.php';

class SimpleReceive
{
    private $__options;

    public function __construct(array $options)
    {
        $this->__options = $options;
    }

    public function listen(array $channels, $timeFrom = null)
    {
        if (is_null($timeFrom)) {
            $timeFrom = microtime(true);
        }

        $quick = new Utility\Quicksilver($this->_getOptions());
        $quick->initMongo();
        $quick->subscribe($channels);

        $microtime = $timeFrom;
        while (1) {
            try {
                $doc = $quick->receive($microtime);
                if (empty($doc)) {
                    // Happens if we're (still) listening to the tailable collection but there were no new
                    // documents added - at least in the current channel
                    echo 'Received empty doc' . PHP_EOL;
                    continue;
                }

                echo 'Received document: ' . print_r($doc, true);

                $type    = isset($doc['message']['type']) ? $doc['message']['type'] : '';
                $command = isset($doc['message']['command']) ? $doc['message']['command'] : '';
                $params  = isset($doc['message']['params']) ? $doc['message']['params'] : array();

                switch ($type) {
                    case 'redis':
                        // Do something with Redis depending on the command and params
                        echo 'Got command for Redis with params: ' . implode(', ', $params) . PHP_EOL;
                        break;

                    default:
                        echo 'Got unrecognized command type: ' . $type . ' with command: ' . $command . PHP_EOL;
                        break;
                }
            } catch (Exception $e) {
                // Can happen for example when there are no matching documents in the collection
                echo 'Exception: ' . (string)$e;
                echo PHP_EOL . 'Waiting for 10 seconds to check again' . PHP_EOL;
                sleep(10);
            }
        }
    }

    protected function _getOptions()
    {
        return $this->__options;
    }
}

$receive = new SimpleReceive(
    array(
        'mongo' =>
        array(
            'hosts'      => 'localhost',
            'replicaSet' => '',
            'database'   => 'test',
            'collection' => 'messages',
            'awaitData'  => true,
        )
    )
);

$receive->listen(
    array(
        'redis',
        'webservers',
    )
);
