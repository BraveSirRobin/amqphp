# Thumper demos.

This   demo  is   a  partial   clone  of   Alvaro   Videla's  [Thumper
demos](https://github.com/videlalvaro/Thumper).   The demos  which are
present here  are compatible with  the original Thumper demos,  and so
should  provide a  good start  point of  users who  want to  move from
php-amqplib to **Amqphp**

There are 2  major differences between this Thumper  and the original,
and   these  boil  down   to  differences   in  the   underlying  Amqp
implementations:

 1.  There are no Thumper base classes, BaseAmqp, BaseConsumer - these
 are  mostly to deal  with setting  up the  broker environment  amd in
 Amqphp you've got  the Factory component which takes  care of most of
 this.
 2.  php-amqplib has  no way of receiving rejected  messages (at least
 none that I'm  aware of), if you look at  the RpcServer and RpcClient
 classes,   these   both   implement   th   amqphp\ChannelEventHandler
 interface,  which   means  that  outgoing  messages   can  be  marked
 mandatory=true  and  immediate=true.  If  such  a  message cannot  be
 immediately delivered, the  broker will send the message  back to the
 originating client

