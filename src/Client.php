<?php

namespace Shuchkin\ReactMySQLi;

class Client
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var \React\EventLoop\TimerInterface
     */
    private $timer;

    /**
     * @var \React\Promise\Deferred[]
     */
    private $deferred = [];

    /**
     * @var \mysqli[]
     */
    private $conn = [];

    /**
     * @var string[]
     */
    private $query = [];


    /**
     * @param \React\EventLoop\LoopInterface $loop
     * @param Pool $connectionPool Pool either connection pool or function that makes connection
     */
    public function __construct( \React\EventLoop\LoopInterface $loop, Pool $connectionPool)
    {
        $this->loop = $loop;
        $this->pool = $connectionPool;
    }
    public static function connect( \React\EventLoop\LoopInterface $loop, $host = 'localhost', $db_name = '', $user = 'root', $password = '', $port = 3306, $max_connections = 10 ) {
		$pool = new Pool( static function () use ( $host, $port, $db_name, $user, $password ) {
				return mysqli_connect( $host, $user, $password, $db_name, $port );
		}, $max_connections );
		return new self( $loop, $pool );
    }


    /**
     * @param string
     * @return \React\Promise\PromiseInterface
     */
    public function query($query)
    {
	    /** @noinspection NullPointerExceptionInspection */
	    return $this->pool->getConnection()->then(function (\mysqli $conn) use ($query) {
            try {
	            $conn->query( $query, MYSQLI_ASYNC );
            } catch ( \RuntimeException $ex ) {
	            $this->pool->free( $conn );

	            // ???
	            return $this->query( $query ); // \React\Promise\reject( $ex );
            }

            $id = spl_object_hash($conn);
            $this->conn[$id] = $conn;
            $this->query[$id] = $query;
            $this->deferred[$id] = $deferred = new \React\Promise\Deferred();

            if (!isset($this->timer)) {
                $this->timer = $this->loop->addPeriodicTimer(0.001, function () {

                    $links = $errors = $reject = $this->conn;
                    mysqli_poll($links, $errors, $reject, 0); // don't wait, just check

                    $each = [ 'links' => $links, 'errors' => $errors, 'reject' => $reject ];
                    foreach ($each as $type => $connects) {
                        /**
                         * @var \mysqli $conn
                         */
                        foreach ($connects as $conn) {
                            $id = spl_object_hash($conn);

                            if (isset($this->conn[$id])) {
                                $deferred = $this->deferred[$id];
                                if ( $type === 'links') {
                                    /**
                                     * @var \mysqli_result $result
                                     */
                                    $result = $conn->reap_async_query();
                                    if ($result === false) {
                                        $deferred->reject(new \Exception($conn->error . '; sql: ' . $this->query[$id]));
                                    } else {
                                        $deferred->resolve(new Result($result, $conn->insert_id, $conn->affected_rows));
                                    }
                                }

                                if ($type === 'errors') {
                                    $deferred->reject(new \Exception($conn->error . '; sql: ' . $this->query[$id]));
                                }

                                if ($type === 'reject') {
                                    $deferred->reject(new \Exception('Query was rejected; sql: ' . $this->query[$id]));
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
