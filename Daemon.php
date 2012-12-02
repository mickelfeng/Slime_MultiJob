<?php
class Daemon
{
    const QUEUE_FETCH_COMMON = 0;
    const QUEUE_FETCH_BLOCK = 1;

    /**
     * @var I_JobQueue
     */
    private $jobQueue;

    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var int
     */
    private $numOfMaxProcess;

    /**
     * @var int
     */
    private $fetchMode;

    /**
     * @var int
     */
    private $interval;

    /**
     * @var $this
     */
    private static $instance;

    private function __construct(I_JobQueue $jobQueue,
                                 Worker $worker,
                                 $numOfMaxProcess,
                                 $fetchMode,
                                 $interval
    )
    {
        if (
            !is_int($numOfMaxProcess) ||
            $numOfMaxProcess < 1 ||
            $fetchMode !== self::QUEUE_FETCH_COMMON ||
            $fetchMode !== self::QUEUE_FETCH_BLOCK ||
            !is_int($interval) ||
            $interval <= 0
        ) {
            exit('ERROR PARAM');
        }
        $this->jobQueue = $jobQueue;
        $this->worker = $worker;
        $this->numOfMaxProcess = $numOfMaxProcess;
        $this->fetchMode = $fetchMode;
        $this->interval = $interval;
    }
    private function __clone(){}

    /**
     * @param I_JobQueue $jobQueue
     * @param Worker $worker
     * @param int $numOfMaxProcess
     * @param int $fetchMode
     * @param int $interval ms
     * @return Daemon
     */
    public static function getInstance(I_JobQueue $jobQueue,
                                       Worker $worker,
                                       $numOfMaxProcess = 10,
                                       $fetchMode = self::QUEUE_FETCH_COMMON,
                                       $interval = 100000
    )
    {
        if (!self::$instance) {
            self::$instance = new self($jobQueue, $worker, $numOfMaxProcess, $fetchMode, $interval);
        }
        return self::$instance;
    }

    public function run()
    {
        while (true) {
            if ($this->fetchMode===self::QUEUE_FETCH_COMMON) {
                list($file, $callback, $param_arr) = $this->jobQueue->pop();
            } else {
                list($file, $callback, $param_arr) = $this->jobQueue->bpop();
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                exit('ERROR FORK');
            } elseif ($pid) {
                ;
            } else {
                $this->worker->pre();
                if (!$this->worker->run($file, $callback, $param_arr)) {
                    $this->jobQueue->push($file, $callback, $param_arr);
                }
                exit();
            }
            if ($this->fetchMode==self::QUEUE_FETCH_COMMON) {
                usleep($this->interval);
            }
        }
    }
}