<?php
 namespace amqphp\protocol\v0_9_1\basic; class GetOkRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField { function getSpecFieldName() { return 'routing-key'; } function getSpecFieldDomain() { return 'shortstr'; } }