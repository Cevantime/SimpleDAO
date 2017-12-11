<?php

namespace SimpleDAO\Generator;
/**
 * Description of DBCodeGenerator
 *
 * @author cevantime
 */
abstract class DBCodeGenerator
{
    /**
     *
     * @var \PDO
     */
    protected $db;
    
    /**
     *
     * @var string 
     */
    protected $table;
    
    /**
     * 
     * @var array $options
     */
    protected $options;

    function __construct(\PDO $db, $table, &$options)
    {
        $this->db = $db;
        $this->table = $table;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public abstract function generate();
    
    public function getDb()
    {
        return $this->db;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function &getOptions()
    {
        return $this->options;
    }

    public function setDb(\PDO $db)
    {
        $this->db = $db;
        return $this;
    }

    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }
    
    public function &getOption($key)
    {
        if( !empty($this->options[$key])){
            return $this->options[$key];
        }
        return null;
    }

}
