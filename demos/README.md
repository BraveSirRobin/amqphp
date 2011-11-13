# Amqphp basic demos

There are 3 scripts to demostrate the most basic usage of **Amqphp**:

 1. get.php  - use  the `basic.get` amqp  method to retrieve  a single
 message
 2. producer.php - use the  `basic.publish` amqp method to publish one
 or more messages.
 3. consumer.php - set up a consumer to receive messages.

The first  of these is  very simple, the  other 2 accept  command line
options  which vary  their behaviour,  use  a --help  switch for  more
information.


## Examples.

First, publish some messages:

    php php producer.php --message="Hello, world" --repeat=5

Now, read these back from the broker using get:

    php get.php

You should have 3 left - read these back using consume:

    php consumer.php --strat="trel 1 0"

The  `--strat`  switch adds  an  *exit  strategy*  to the  connection,
without this the script will never end (until you kill it with Ctrl-C,
of course).