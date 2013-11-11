quicksilver
===========

A MongoDB realtime buffered messaging library


Requirements
============

In order to work, Quicksilver needs the Mongo extension for PHP.

In MongoDB we need a capped collection. 
http://docs.mongodb.org/manual/core/capped-collections/

    db.createCollection("messages", { capped : true, size : 5242880, max : 500 } )



Using Quicksilver
=================

The main idea of this is to use MongoDB as a system to send messages to
different "daemons" that are listening to execute any command sent through
the communication channel (tailable collection)

In order to explain the main idea, we will provide an example how this can be 
used.

Sending a new event through Quicksilver
-----------------------------------

First we have to set the configuration for the connection with MongoDB

        <?php
        $options['mongo'] = array(
            'mongo' =>
            array(
                'hosts' => 'localhost',
                'replicaSet' => '',
                'database' => 'test',
            ),
        );
        $options['mongo']['collection'] = 'messages';
        $quick = new Quicksilver($options);
        $quick->initMongo();
        $message = array('type' => 'redis', 'command' => 'flushAll', 'params' => array());
        $quick->send($message, array('this', 'that'));

The message can be whatever we want to send. For example an action to be run for
the daemons. This example is telling the daemons to do a flushAll on the Redis
servers. This can be very useful if you need to refresh your cache after some
administration task across all your servers. But this is just one example.
The idea is to send whatever you need.

We decide to use just 1 'tailable' -capped- collection in Mongo. So in order to 
split the actions between different services that are listening in the same 
collection we are sending to different "channels". Our concept of "channels" is
very simple and is just an array that we send as second parameter in the send
method. Channels are implemented to "filter" messages, so that a particular group
of listening clients get these messages, while clients that don't belong to this
group do not get these messages. Using the previous example, we may want to send a
command to all clients that are listening to the "redis" channel, but not any clients
which are not subscribed to this channel - clients which don't have any redis server
available. Then we may have an "webservers" channel, to which all webservers are
subscribed, and we could send a "restart nginx" message which would be executed on all
of those servers.


Listening the new event from our 'daemon'
----------------------------------------

In order to listen our messages we need to create a tailable cursor. This will 
be listening to any new insert in the collection. All the daemons that are listening
on that channel will get the same message. So all of them will be able to do the
same action if need they to.


        <?php
        $options['mongo'] = array(
            'mongo' =>
            array(
                'hosts' => 'localhost',
                'replicaSet' => '',
                'database' => 'test',
            ),
        );
        $options['mongo']['collection'] = 'messages';
        $options['mongo']['awaitData'] = true;
        $quick = new Quicksilver($options);
        $quick->initMongo();
        $quick->subscribe(array('this', 'that'));

We have an option which determines whether we want to keep waiting (awaitData). If
this is set to false, we will execute just 1 action. Otherwise will keep running
endlessly waiting for new events.
