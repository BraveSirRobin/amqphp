<?php
 namespace amqphp\protocol\v0_9_1\exchange; class DeleteExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField { function getSpecFieldName() { return 'exchange'; } function getSpecFieldDomain() { return 'exchange-name'; } function validate($subject) { return (parent::validate($subject) && ! is_null($subject)); } }