<?php
/**
 * Helper script to convert the default-value RMQ JSON data in to the regular amqp 091 XML.
 * WHY OH WHY OH WHY AM I FORCED TO DO THIS!?!?!?  Using JSON instead of the standard xml
 * is a REALLY STUPID decision by the Rabbiters!!!
 */

$dickheadJsonFile = '/home/robin/Downloads/rabbitmq-java-client-2.0.0/codegen/amqp-rabbitmq-0.9.1.json';
$ampqFile = 'rabbitMq.defaults.xml';

if (is_file($ampqFile)) {
    die("\nTarget file already exists!\n");
}


$j = json_decode(file_get_contents($dickheadJsonFile));

$w = new XMLWriter;
$w->openURI($ampqFile);
$w->setIndent(true);
$w->setIndentString('  ');


$w->startElement('amqpDefaults');
$w->writeAttribute('targetImpl', 'RabbitMQ 0.9.1');


foreach ($j->classes as $class) {
    $w->startElement('class');
    $w->writeAttribute('name', $class->name);
    $w->writeAttribute('id', $class->id);
    if (isset($class->properties)) {
        // Class fields
        foreach ($class->properties as $clsProp) {
            $w->startElement('field');
            $w->writeAttribute('name', $clsProp->name);
            $w->endElement(); // field
        }
    }

    foreach ($class->methods as $meth) {
        $w->startElement('method');
        $w->writeAttribute('name', $meth->name);
        $w->writeAttribute('id', $meth->id);

        foreach ($meth->arguments as $arg) {
            $w->startElement('field');
            $w->writeAttribute('name', $arg->name);
            // NOTE: Some default values are an empty JSON objects (?!) - treat these as empty string
            if (isset($arg->{"default-value"}) && ! is_object($arg->{"default-value"})) {
                //printf("Write field %s of method %s class %s\n", $arg->name, $meth->name, $class->name);
                $w->writeAttribute("default-value", $arg->{"default-value"});
            } else {
                $w->writeAttribute("default-value", '');
            }
            $w->endElement(); // field
        }
        $w->endElement(); // method
    }
    $w->endElement(); // class
}

$w->endElement(); // amqpDefaults
$w->flush();
echo "\nDone\n";