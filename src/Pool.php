<?php

namespace Shuchkin\ReactMySQLi;

class Pool
{
    /**
     * @var callable
     * @return \mysqli
     */
    private $makeConnection;

    /**
     * @var int
     */
    private $maxConnections;

    /**
     * pool of idle connections
     * @var \mysqli[]
     */
    private $idlePool;
	/**
	 * pool of idle connections
	 * @var \mysqli[]
	 */
    private $busyPool;



    /** array of Deferred objects waiting to be resolved with connection */
    private $waiting = [];


    public function __construct(callable $makeConnection, $maxConnections = 10)
    {
        $this->makeConnection = $makeConnection;
        $this->maxConnections = $maxConnections;
        $this->busyPool = [];
        $this->idlePool = [];
	    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }


    public function getConnection()
    {
        foreach( $this->idlePool as $k => $v ) {
	        if ( $v->errno === 2006 ) { // MySQL server has gone away
	            unset($this->idlePool[$k]);
	        }
        }
	    foreach( $this->busyPool as $k => $v ) {
		    if ( $v->errno === 2006 ) { // MySQL server has gone away
			    unset($this->busyPool[$k]);
		    }
	    }
	    $cnt_busy = \count($this->busyPool);
	    $cnt_idle = \count($this->idlePool);
	    if ( $cnt_busy + $cnt_idle >= $this->maxConnections ) {
		    $deferred = new \React\Promise\Deferred();
		    $this->waiting[] = $deferred;
		    return $deferred->promise();
	    }
		if ( $cnt_idle ) {
			$conn = array_shift( $this->idlePool );
        } else {
			/**
			 * @var \mysqli|false $conn
			 */
			try {
				$conn = \call_user_func( $this->makeConnection );
			} catch ( \Exception $ex ) {
				return \React\Promise\reject( $ex );
			}
		}
		$this->busyPool[] = $conn;
        return \React\Promise\resolve($conn);
    }


    public function free( \mysqli $conn)
    {
        if ($conn->errno !== 2006) { // MySQL server has gone away
            $k = array_search($conn, $this->busyPool, true);
            if ( $k !== false ) {
                unset($this->busyPool[$k]);
            }
            $this->idlePool[] = $conn;
        }

        if ( \count($this->waiting) ) {
            $deferred = array_shift( $this->waiting );
	        /** @noinspection NullPointerExceptionInspection */
	        $this->getConnection()->done(function ($conn) use ($deferred) {
                /**
                 * @var \React\Promise\Deferred $deferred
                 */
                $deferred->resolve($conn);
            }, [$deferred, 'reject']);
        }
    }
}
