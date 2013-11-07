<?php

require_once '../Quicksilver.php';
use Utility\Quicksilver;

class SimpleSend
{
    private $__options;

    public function __construct(array $options)
    {
        $this->__options = $options;
    }

    protected function _getOptions()
    {
        return $this->__options;
    }

    public function send($message)
    {
        $quick = new Quicksilver($this->_getOptions());
        $quick->initMongo();
        $quick->send($message, array('redis', 'foobar'));
    }
}

$send = new SimpleSend(
    array(
        'mongo' =>
        array(
            'hosts'      => 'localhost',
            'replicaSet' => '',
            'database'   => 'test',
            'collection' => 'messages',
        )
    )
);

$send->send(
    $message = array('type' => 'redis', 'command' => 'flushAll', 'params' => array())
);
