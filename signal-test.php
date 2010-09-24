<?php

// Try to catch a signal from bash to close the socket properly.

$handler = function ($sig) {
    printf("Caught signal %d\n", $sig);
    exit(0);
};

/*
pcntl_signal(SIG_IGN, $handler);
//pcntl_signal(SIG_DFL, $handler);
//pcntl_signal(SIG_ERR, $handler);
pcntl_signal(SIGHUP, $handler);
*/
pcntl_signal(SIGINT, $handler);
/*
pcntl_signal(SIGQUIT, $handler);
pcntl_signal(SIGILL, $handler);
pcntl_signal(SIGTRAP, $handler);
pcntl_signal(SIGABRT, $handler);
pcntl_signal(SIGIOT, $handler);
pcntl_signal(SIGBUS, $handler);
pcntl_signal(SIGFPE, $handler);
//pcntl_signal(SIGKILL, $handler);
pcntl_signal(SIGUSR1, $handler);
pcntl_signal(SIGSEGV, $handler);
pcntl_signal(SIGUSR2, $handler);
pcntl_signal(SIGPIPE, $handler);
pcntl_signal(SIGALRM, $handler);
pcntl_signal(SIGTERM, $handler);
pcntl_signal(SIGSTKFLT, $handler);
pcntl_signal(SIGCLD, $handler);
pcntl_signal(SIGCHLD, $handler);
pcntl_signal(SIGCONT, $handler);
//pcntl_signal(SIGSTOP, $handler);
pcntl_signal(SIGTSTP, $handler);
pcntl_signal(SIGTTIN, $handler);
pcntl_signal(SIGTTOU, $handler);
pcntl_signal(SIGURG, $handler);
pcntl_signal(SIGXCPU, $handler);
pcntl_signal(SIGXFSZ, $handler);
pcntl_signal(SIGVTALRM, $handler);
pcntl_signal(SIGPROF, $handler);
pcntl_signal(SIGWINCH, $handler);
pcntl_signal(SIGPOLL, $handler);
pcntl_signal(SIGIO, $handler);
pcntl_signal(SIGPWR, $handler);
pcntl_signal(SIGSYS, $handler);
pcntl_signal(SIGBABY, $handler);
//pcntl_signal(SIG_BLOCK, $handler);
pcntl_signal(SIG_UNBLOCK, $handler);
pcntl_signal(SIG_SETMASK, $handler);
*/
$i = 0;
while ($i < 100) {
    printf("Read Sigs\n");
    pcntl_signal_dispatch();
    usleep(500000);
    $i++;
}
printf("Done\n");