<?php
namespace Shuchkin\ReactMySQLi;

class Result
{
    /**
     * @var array
     */
    public $rows = [];

    /**
     * Mysql last_insert_id
     * @var int
     */
    public $insert_id;

    /**
     * Number affected rows
     * @var int
     */
    public $affected_rows;

    public function __construct($res, $insert_id, $affected_rows)
    {
        if ($res instanceof \mysqli_result) {
            while ($row = $res->fetch_object()) {
                $this->rows[] = $row;
            }
            $res->free();
        }

        $this->insert_id = $insert_id;
        $this->affected_rows = $affected_rows;
    }

    /**
     * Return all rows
     * @return array
     */
    public function all()
    {
        return $this->rows;
    }

    /**
     * Return one row
     * @return mixed
     */
    public function one()
    {
        return current($this->all());
    }

    /**
     * Values of first column
     * @return array
     */
    public function column()
    {
        $res = [];
        foreach ($this->all() as $row) {
            $res[] = current($row);
        }
        return $res;
    }

    /**
     * Value of first field in first row
     * @return mixed
     */
    public function scalar()
    {
        return count($this->rows) ? current( $this->rows[0] ) : false;
    }

    /**
     * Is empty result set?
     * @return bool
     */
    public function exists()
    {
        return !empty($this->rows);
    }
}
