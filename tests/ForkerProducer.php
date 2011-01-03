<?php


class ForkerProducer extends Forker
{

    private $smallMsg;
    private $largeMsg;
    private $sigHandled = false;

    function start () {
        echo "ForkerProducer {$this->n} starts\n";
        if (! pcntl_signal(SIGINT, array($this, 'sigHand'))) {
            echo "Failed to install SIGINT in producer {$this->n}\n";
        }
        if (! pcntl_signal(SIGTERM, array($this, 'sigHand'))) {
            echo "Failed to install SIGTERM in producer {$this->n}\n";
        }
        $this->initConnection();
        $this->prepareAndRun();
        if ( ! $this->sigHandled) {
            $this->shutdownConnection();
        }
        echo "ForkerProducer {$this->n} exits\n";
    }

    /**
     * Main entry point.
     */
    function prepareAndRun () {
        // Prepare the large and small messages
        $chan = $this->prepareChannel();
        $this->smallMsg = $chan->basic('publish', array('content-type' => 'text/plain',
                                                        'content-encoding' => 'UTF-8',
                                                        'routing-key' => '',
                                                        'mandatory' => false,
                                                        'immediate' => false,
                                                        'exchange' => $this->fParams['exchange']), 'small message says hi!');
        $this->largeMsg = $chan->basic('publish', array('content-type' => 'text/plain',
                                                        'content-encoding' => 'UTF-8',
                                                        'routing-key' => '',
                                                        'mandatory' => false,
                                                        'immediate' => false,
                                                        'exchange' => $this->fParams['exchange']), file_get_contents(__DIR__ . '/data/large-file.txt'));
        $nMsgs = rand(150, 200);
        $b = '';
        $perc = $this->fParams['prodSmallMsgPercent'];
        for ($i = 0; $i < 1; $i++) {
        //while (true) {
            if (rand(0, 99) > $perc) {
                $b .= 'L';
                $chan->invoke($this->largeMsg);
            } else {
                $b .= 's';
                $chan->invoke($this->largeMsg);
            }
            pcntl_signal_dispatch();
            if ($this->sigHandled) {
                return;
            }
        }
        printf("Test producer {$this->n} completed producing:\n%s\n", $b);
    }



    function sigHand () {
        if (! $this->sigHandled) {
            echo " --Signal handler for producer {$this->n}\n";
            $this->sigHandled = true;
            $this->shutdownConnection();
        }
    }

}