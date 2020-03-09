# React MySQLi 0.1

Asynchronous & non-blocking MySQLi driver for [ReactPHP](https://github.com/reactphp/react).

Require [php-mysqlnd](http://php.net/manual/ru/book.mysqlnd.php) extension

## Install

```bash
composer require shuchkin/react-mysqli
```

## CONNECTION and SELECT
```php
$loop = \React\EventLoop\Factory::create();

$db = \Shuchkin\ReactMySQLi\Client::connect($loop, 'localhost', 'root', '', 'my_db', 3 );

// select
$db->query('SELECT id,name,email FROM user')->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->all()); // array
    },
    function ( \Exception $ex ) {
        trigger_error( $ex->getMessage() );
    }
);
$loop->run();
```
```
Array
(
    [0] => stdClass Object
        (
            [id] => 1
            [name] => Gianni Rodari
            [email] => gianni.rodari@example.com
        )

    [1] => stdClass Object
        (
            [id] => 2
            [name] => Rikki-Tikki-Tavi
            [email] => mangoose@example.com
        )

)
```
## INSERT
```php
$db->query("INSERT INTO user SET name='Sergey',email='sergey.shuchkin@gmail.com'")->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->insert_id); // 12345
    },
    function ( \Exception $ex ) {
        trigger_error( $ex->getMessage() );
    }
);
```
### UPDATE
```php
$db->query("UPDATE user SET email='sergey@example.com' WHERE id=12345")->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->affected_rows);
    },
    function ( \Exception $ex ) {
        trigger_error( $ex->getMessage() );
    }
);
```
### DELETE
```php
$db->query('DELETE FROM user WHERE id=12345')->then(
    function (\Shuchkin\ReactMySQLi\Result $result) {
        print_r($result->affected_rows);
    },
    function ( \Exception $ex ) {
        trigger_error( $ex->getMessage() );
    }
);
```