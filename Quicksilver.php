<?php

namespace Utility;

use Utility\Hash,
    Exception,
    MongoDB,
    MongoDate;

/**
 * Quicksilver is a PHP library used to provide a simple method of broadcasting 
 * "messages" in a distributed server architecture. Messages can be sent and 
 * received by any box with access to the messaging server (MongoDB using 
 * tailable collections).
 * This can be very useful if you need to excecute the same task accross many 
 * servers. 
 * In order to make it work properly, servers must be syncronrized (ntp) 
 * @author EDM <EDMDev@excitedigitalmedia.com>
 * @category Utility
 * @link http://screens/index.php?title=Quicksilver Wiki site
 */
class Quicksilver
{

    /**
     * The options to initialize the class and the Mongo connection
     * @var array 
     */
    private $__options = [];
    /**
     * The channels where this class will be listening
     * @var array 
     */
    private $__channels = [];
    /**
     * The Mongo Cursor, can be awaiting data or not depending the options 
     * defined when the class is instantiated
     * @var MongoCursor 
     */
    private $__cursor = null;
    /**
     * The Mongo Collection that has the tailable behaviour
     * @var MongoCollection 
     */
    private $__coll = null;

    /**
     * The Mongo DataBase
     * @var type MongoDB
     */
    private $__mongoDb = null;

    /**
     * The constructor will set the options if an array with options is used 
     * as parameter
     * @param array $options the options to stablish a MongoDB connection
     */
    public function __construct($options = array())
    {
        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Returns a reference to a tailable cursor if already exists, 
     * otherwise performs query and sets $this->__cursor
     * Depending the value set in the options, this cursor can be awaiting 
     * for data or not. 
     * @param MongoTime $timestamp The moment that you want to start to check the collection
     * @return Mon goCursor
     * @internal 
     */
    protected function &_getCursor($timestamp = 0)
    {

        if (!isset($this->__cursor)) {
            if (!isset($this->__coll)) {
                throw new Exception("There is not active mongo collection");
            }
            $conditions = [
                'timestamp' => ['$gt' => new MongoDate($timestamp)],
                'channels' => ['$in' => $this->getSubscribed()],
            ];
            $this->__cursor = $this->__coll
                    ->find($conditions)
                    ->tailable(true)
                    ->awaitData($this->__options['mongo']['awaitData']);
        }
        return $this->__cursor;
    }

    /**
     * We need to nullify the internal property @var $this->__cursor
     * under some scenarios such as the cursor is dead
     * @internal 
     */
    protected function _clearCursor()
    {
        $this->__cursor = null;
    }

    /**
     * This method initialize the connection to MongoDB and set a MongoDB 
     * object as an internal @var $this->__mongoDB. This method return a 
     * MongoCollection. If an existent object MongoDB is send as parameter, 
     * we use that object internaly, otherwise, we need to create a new instance
     * using the options configuration array. 
     * @param MongoDB $mongoDb
     * @return MongoCollection
     * @throws Exception
     */
    public function initMongo(MongoDB $mongoDb = null)
    {
        if (!$mongoDb) {
            if (!is_array($this->__options)) {
                throw new Exception("You don't have set the options for Quicksilver");
            }
            $mongoConf = $this->__options['mongo'];
            $mongo = new \Mongo('mongodb://' . $mongoConf['hosts'], array('replicaSet' => $mongoConf['replicaSet']));
            $this->__mongoDb = $mongo->selectDB($mongoConf['database']);
        }
        $this->__mongoDb = $mongoDb;
        return $this->__coll = $mongoDb->selectCollection($this->__options['mongo']['collection']);
    }

    /**
     * We use an array with configuration to connect to Mongo
     * @param array $options
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        if (!is_array($options)) {
            throw new Exception("Quicksilver options must be an array");
        }
        if (!isset($options['mongo'])) {
            throw new Exception("Parameter 'mongo' is required in options array");
        }
        if(!isset($options['mongo']['awaitingData'])){
            $options['mongo']['awaitingData'] = false;
        }
        $this->__options = Hash::merge($this->__options, $options);
    }

    /** Sends a message to an array of channels * 
     * 
     * @param mixed array|string $msg Is the message that you want to store. 
     * @param array $to Is the set of channels where you want to listen. The method
     *  $this->recieve will check only messages that are in this set. This can be very 
     * usefull to use the same Mongo Collection, but sending different sort of m
     * messages or actions to different workers
     * @return boolean True if 
     * @throws Exception when is not possible to save the current message
     */
    public function send($msg, array $to)
    {
        $message = [
            'message' => $msg,
            'timestamp' => new MongoDate(microtime(true)),
            'channels' => $to,
        ];

        if (!$this->__coll->save($message)) {
            throw new Exception("It wasn't able to save to the channel '$channel'");
        }
        return true;
    }

    /** Subscribes the class to a given channel. Only messages sent to this 
     * channel will be received * 
     * @param mixed array|string. This parameter is the channels where you want
     * to listen. Will set the internal $this->__channel, using as keys the set 
     * of channels
     * @return void
     */
    public function subscribe($channels)
    {
        if (is_array($channels)) {
            foreach ($channels as $channel) {
                $this->__channels[(string) $channel] = true;
            }
        } else {
            $this->__channels[(string) $channels] = true;
        }
    }
    
    /**
     * @return array The channels where is listening the current instance
     */
    public function getSubscribed()
    {
        return array_keys($this->__channels);
    }

    /** Attempt to receive a message from cursor. Returns immediately if 
     * $this->__options['mongo']['awaitingData'] == false, otherwise waits 
     * for a message before returning 
     * 
     * @param float $timestamp <i>microtime()</i>
     * @return array Returns an empty array in case the $cursor has not a next()
     * element. Otherwise returns the next() element and move the internal cursor
     * for $this->__cursor to the next element.
     * @throws Exception
     */
    public function receive($timestamp = 0)
    {
        $cursor = $this->_getCursor($timestamp);
        if ($cursor->dead()) {
            $this->_clearCursor();
            throw new Exception('Cursor is dead or returned no results');
        }
        if ($cursor->hasNext()) {
            return $cursor->getNext();
        }
        return [];
    }

}
