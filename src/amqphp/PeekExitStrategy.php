<?php


class PeekExitStrategy implements ExitStrategy
{
    private $conn;
    /**
     * Called once when the  select mode is first configured, possibly
     * with other parameters
     */
    function configure ($sMode) {
    }

    /**
     * Called once  per select loop run, calculates  initial values of
     * select loop timeouts.
     */
    function init (Connection $conn) {
        $this->conn = $conn;
    }

    /**
     * Forms a "chain of responsibility" - each
     *
     * @return   mixed    True=Loop without timeout, False=exit loop, array(int, int)=specific timeout
     */
    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        return $this->conn->socketPeek() ? $prev : false;
    }

    /**
     * Notification that the loop has exited
     */
    function complete () {
    }
}