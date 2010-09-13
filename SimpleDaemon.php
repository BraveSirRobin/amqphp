<?php

/**
 * A simple forking Daemon, initially based on this:
 * http://www.php.net/manual/en/function.pcntl-wait.php#98710
 */
class SimpleDaemon
{
    public $maxProcesses = 25;
    protected $jobsStarted = 0; 
    protected $currentJobs = array();
    protected $signalQueue=array();    
    protected $parentPID;
    
    public function __construct(){
        echo "constructed \n";
        $this->parentPID = getmypid();
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
    }
    
    /** 
     * Run the Daemon
     */
    public function run(){
        echo "Running \n";
        for($i=0; $i<10000; $i++){
            $jobID = rand(0,10000000000000);
            $launched = $this->launchJob($jobID);
        }
        
        //Wait for child processes to finish before exiting here
        while(count($this->currentJobs)){
            echo "Waiting for current jobs to finish... \n";
            sleep(1);
        }
    }
    
    /** 
     * Launch a job from the job queue
     */
    protected function launchJob($jobID){
        $pid = pcntl_fork();
        if($pid == -1){
            //Problem launching the job
            error_log('Could not launch new job, exiting');
            return false;
        }
        else if ($pid){
            // Parent process
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if 
            // the child script executes quickly enough!
            //
            $this->currentJobs[$pid] = $jobID;
            
            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if(isset($this->signalQueue[$pid])){
                echo "found $pid in the signal queue, processing it now \n";
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        } else {
            //Forked child, do your deeds.... 
            $exitStatus = 0; //Error code if you need to or whatever
            echo "Doing something fun in pid ".getmypid()."\n";
            exit($exitStatus);
        }
        return true;
    }
    
    public function childSignalHandler($signo, $pid=null, $status=null){
        
        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended
        if (!$pid){
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        
        //Make sure we get all of the exited children
        while ($pid > 0){
            if ($pid && isset($this->currentJobs[$pid])){
                $exitCode = pcntl_wexitstatus($status);
                if ($exitCode != 0){
                    echo "$pid exited with status ".$exitCode."\n";
                }
                unset($this->currentJobs[$pid]);
            } else if ($pid){
                //Oh no, our job has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                echo "..... Adding $pid to the signal queue ..... \n";
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
    }
}