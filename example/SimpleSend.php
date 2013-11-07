<?php

require_once '../Quicksilver.php';
use Utility\Quicksilver;

class SimpleSend
{

    public function getOptions()
    {
        $options = array(
            'mongo' =>
            array(
                'hosts' => 'localhost',
                'replicaSet' => '',
                'database' => 'test',
            ),
        );
        $options['mongo']['collection'] = 'messages';
        return $options;
    }

    public function send()
    {
        $quick = new Quicksilver($this->getOptions());
        $quick->initMongo();
        $message = array('type' => 'redis', 'command' => 'flushAll', 'params' => array());
        $quick->send($message, array('this', 'that'));
    }

}

$send = new SimpleSend();

$send->send();
