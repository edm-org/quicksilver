<?php

namespace Utility;

use Exception,
    MongoDB,
    MongoDate;

/**
 * Quicksilver is a PHP library used to provide a simple method of broadcasting
 * "messages" in a distributed server architecture. Messages can be sent and
 * received by any box with access to the messaging server (MongoDB using
 * tailable collections).
 * This can be useful if you want to execute the same task across multiple servers
 * In order to make it work properly, servers must have synchronized clocks
 * @author EDM <EDMDev@excitedigitalmedia.com>
 * @category Utility
 */
class Quicksilver
{

    /**
     * The options to initialize the class and the Mongo connection
     * @var array
     */
    private $__options = array();

    /**
     * The channels to which this class will be listening/subscribed
     * @var array
     */
    private $__channels = array();

    /**
     * The Mongo Cursor, can be awaiting data or not depending the options
     * defined when the class is instantiated
     * @var MongoCursor
     */
    private $__cursor = null;

    /**
     * The Mongo Collection which has the tailable behaviour
     * @var MongoCollection
     */
    private $__coll = null;

    /**
     * The MongoDB object
     * @var type MongoDB
     */
    private $__mongoDb = null;

    /**
     * The constructor will set the options if an array with options is used
     * as parameter
     * @param array $options the options to establish a MongoDB connection
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
     * Depending on the value set in the options, this cursor may
     * be awaiting data
     *
     * @param int $timestamp The timestamp from which you want to receive all
     *                       messages (filtered by channel) that are written
     *                       to the collection. This is usually 'now', but can
     *                       back-date in cases like if your server went down
     *                       and you want to perform historically queued actions.
     *                       This is subject to the cap of your MongoCollection
     *
     * @return null|MongoCursor
     * @throws \Exception
     * @internal
     */
    protected function &_getCursor($timestamp = 0)
    {
        if (!isset($this->__cursor)) {
            if (!isset($this->__coll)) {
                throw new Exception('There is no active mongo collection');
            }
            $conditions = array(
                'timestamp' => array('$gt' => new MongoDate($timestamp)),
                'channels' => array('$in' => $this->getSubscribed()),
            );
            $this->__cursor = $this->__coll
                    ->find($conditions)
                    ->tailable(true)
                    ->awaitData($this->__options['mongo']['awaitData']);
        }
        return $this->__cursor;
    }

    /**
     * We need to nullify the internal property @var $this->__cursor
     * under some scenarios like when the cursor is dead
     * @internal
     */
    protected function _clearCursor()
    {
        $this->__cursor = null;
    }

    /**
     * This method initialize the connection to MongoDB and set a MongoDB
     * object as an internal @var $this->__mongoDb. This method returns a
     * MongoCollection. If an existent object MongoDB is sent as parameter,
     * we use that object internally, otherwise, we need to create a new instance
     * using the options configuration array.
     * @param MongoDB $mongoDb
     * @return MongoCollection
     * @throws \Exception
     */
    public function initMongo(MongoDB $mongoDb = null)
    {
        if (!$mongoDb) {
            if (!is_array($this->__options) ||
                empty($this->__options['mongo'])) {
                throw new Exception('Could not initiate Mongo because you have not set the options for Quicksilver');
            }
            $mongoConf = $this->__options['mongo'];
            $mongo = new \MongoClient('mongodb://' . $mongoConf['hosts'], array('replicaSet' => $mongoConf['replicaSet']));
            $mongoDb = $mongo->selectDB($mongoConf['database']);
        }
        $this->__mongoDb = $mongoDb;
        return $this->__coll = $mongoDb->selectCollection($this->__options['mongo']['collection']);
    }

    /**
     * We use an array with configuration options to connect to Mongo
     *
     * @param array $options
     * @return void
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        if (!isset($options['mongo'])) {
            throw new Exception('Parameter "mongo" is required in options array');
        }
        if (!isset($options['mongo']['awaitingData'])) {
            $options['mongo']['awaitingData'] = false;
        }
        $this->__options = array_merge_recursive($this->__options, $options);
    }

    /**
     * Send a message to an array of channels
     *
     * @param mixed $msg The message you want to send (array or string)
     * @param array $to  The set of channels to which you want to send your message. The
     *                   method $this->receive will only check messages that are in this
     *                   set. This way we can use the same tailable Mongo Collection, but
     *                   send messages targeted only to subscribers of the channel,
     *                   e.g. various different workers.
     *
     * @return bool
     * @throws \Exception When is not possible to save the current message
     */
    public function send($msg, array $to)
    {
        $message = array(
            'message' => $msg,
            'timestamp' => new MongoDate(microtime(true)),
            'channels' => $to,
        );

        if (!$this->__coll->save($message)) {
            $channels = implode(',', $to);
            throw new Exception("Unable to save to the channel '{$channels}'");
        }
        return true;
    }

    /**
     * Subscribe the object to a given channel or array of channels. Only
     * messages sent to this/these channel(s) will be received
     *
     * @param mixed array|string. This parameter is the channels to which you want
     *              to listen. Will set the internal $this->__channel, using as keys
     *              the set of channels
     *
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
     * Return the channels we're currently subscribed to
     *
     * @return array The channels to which we're currently subscribed
     */
    public function getSubscribed()
    {
        return array_keys($this->__channels);
    }

    /**
     * Attempt to receive a message from cursor. Return immediately if
     * $this->__options['mongo']['awaitingData'] == false, otherwise wait
     * for a message before returning
     *
     * @param int $timestamp <i>microtime()</i>
     *
     * @return array Returns an empty array in case the $cursor does not have a next
     *              element. Otherwise returns the next() element and move the internal cursor
     *              for $this->__cursor to the next element.
     * @throws \Exception
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
        return array();
    }
}
