# React MySQLi 0.1

Asynchronous & non-blocking MySQLi driver for [ReactPHP](https://github.com/reactphp/react).

Require [php-mysqlnd](http://php.net/manual/ru/book.mysqlnd.php) extension

## Install

```bash
composer require shuchkin/react-mysqli
```

## Example

```php
$loop = \React\EventLoop\Factory::create();

$mysql = \Shuchkin\ReactMySQLi\Client::connect($loop, 'localhost', 'root', '', 'my_db', 3306, 3 );

// select
$mysql->query('select * from user')->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->all()); // array
    },
    function ( \Exception $ex ) {
        error_log( $ex->getMessage() );
    }
);

// insert
$mysql->query("INSERT INTO user SET name='Sergey',email='sergey.shuchkin@gmail.com'")->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->insert_id); // 12345
    },
    function ( \Exception $ex ) {
        error_log( $ex->getMessage() );
    }
);

// update
$mysql->query("UPDATE user SET email='sergey@example.com' WHERE id=12345")->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->affected_rows);
    },
    function ( \Exception $ex ) {
        error_log( $ex->getMessage() );
    }
);

// update
$mysql->query("DELETE FROM user WHERE id=12345")->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->affected_rows);
    },
    function ( \Exception $ex ) {
        error_log( $ex->getMessage() );
    }
);


$loop->run();
```