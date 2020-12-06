<?php

namespace Shuchkin\ReactMySQLi;

use Exception;
use mysqli;
use mysqli_result;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;

class Client
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var TimerInterface
     */
    private $timer;

    /**
     * @var Deferred[]
     */
    private $deferred = [];

    /**
     * @var mysqli[]
     */
    private $conn = [];

    /**
     * @var string[]
     */
    private $query = [];


    /**
     * @param LoopInterface $loop
     * @param Pool $connectionPool Pool either connection pool or function that makes connection
     */
    public function __construct( LoopInterface $loop, Pool $connectionPool)
    {
        $this->loop = $loop;
        $this->pool = $connectionPool;
    }
    public static function connect( LoopInterface $loop, $host = 'localhost', $user = 'root', $password = '', $db_name = '', $max_connections = 10 )
    {
        $a = explode(':',$host);
        if ( $a[0] === 'p' ) {
            array_shift( $a );
        }
        $host = array_shift($a);
        $port = count($a) ? $a[0] : 3306;
		$pool = new Pool( static function () use ( $host, $port, $db_name, $user, $password ) {
				return mysqli_connect( $host, $user, $password, $db_name, $port );
		}, $max_connections );
		return new self( $loop, $pool );
    }


    /**
     * @param string
     * @return PromiseInterface
     */
    public function query($query)
    {
	    return $this->pool->getConnection()->then(function (mysqli $conn) use ($query) {
            try {
	            $conn->query( $query, MYSQLI_ASYNC );
            } catch ( RuntimeException $ex ) {
	            $this->pool->free( $conn );

	            // ???
	            return $this->query( $query ); // \React\Promise\reject( $ex );
            }

            $id = spl_object_hash($conn);
            $this->conn[$id] = $conn;
            $this->query[$id] = $query;
            $this->deferred[$id] = $deferred = new Deferred();

            if (!isset($this->timer)) {
                $this->timer = $this->loop->addPeriodicTimer(0.001, function () {

                    $links = $errors = $reject = $this->conn;
                    mysqli_poll($links, $errors, $reject, 0); // don't wait, just check

                    $each = [ 'links' => $links, 'errors' => $errors, 'reject' => $reject ];
                    foreach ($each as $type => $connects) {
                        /**
                         * @var mysqli $conn
                         */
                        foreach ($connects as $conn) {
                            $id = spl_object_hash($conn);

                            if (isset($this->conn[$id])) {
                                $deferred = $this->deferred[$id];
                                if ( $type === 'links') {
                                    /**
                                     * @var mysqli_result $result
                                     */
                                    $result = $conn->reap_async_query();
                                    if ($result === false) {
                                        $deferred->reject(new Exception($conn->error . '; sql: ' . $this->query[$id]));
                                    } else {
                                        $deferred->resolve(new Result($result, $conn->insert_id, $conn->affected_rows));
                                    }
                                }

                                if ($type === 'errors') {
                                    $deferred->reject(new Exception($conn->error . '; sql: ' . $this->query[$id]));
                                }

                                if ($type === 'reject') {
                                    $deferred->reject(new Exception('Query was rejected; sql: ' . $this->query[$id]));
                                }

                                unset($this->deferred[$id], $this->conn[$id], $this->query[$id]);
                                $this->pool->free($conn);
                            }
                        }
                    }

                    if (empty($this->conn)) {
	                    $this->loop->cancelTimer($this->timer);
                        $this->timer = null;
                    }
                });
            }

            return $deferred->promise();
        });
    }
}
