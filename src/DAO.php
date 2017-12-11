<?php

namespace SimpleDAO;

class DAO
{

    /**
     *
     * @var \PDO an connected instance of PDO
     */
    protected $db;

    /**
     *
     * @var string the name of the table to use for this DAO
     */
    protected $tableName;

    /**
     *
     * @var array
     */
    protected $tableColumns;

    /**
     *
     *  @var string
     */
    protected $entityClassName;

    /**
     *
     * @var string 
     */
    protected $primary = 'id';

    /**
     *
     * @var array
     */
    protected $hasOne;

    /**
     *
     * @var boolean
     */
    private $_hasOneParsed = false;

    /**
     * 
     * @var array
     */
    protected $options;
    private static $_DAO_CACHE;

    /**
     *
     * @param \PDO $db
     * @param string $tableName
     */
    public function __construct(\PDO $db, $options = null)
    {
        $this->db = $db;

        if ($this->tableName) {
            $tableName = $this->tableName;
        } else {
            $tableName = $this->guessTableName();
        }

        if (!$options) {
            $this->setOptions([]);
        } else {
            $this->setOptions($options);
        }

        $this->setTableName($tableName);

        self::$_DAO_CACHE[spl_object_hash($this->db)][get_class($this)] = $this;
    }

    /**
     * 
     * @return string
     */
    protected function guessTableName()
    {
        $className = get_class($this);
        $segments = explode('\\', $className);
        $className = array_pop($segments);
        return str_replace('_dao', '', $this->snakify($className));
    }

    /**
     * 
     * @param string $str the string to transform to snake case
     * @return string
     */
    protected function snakify($str)
    {
        preg_match_all('/([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)/', $str, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    /**
     * 
     * @param string $str
     * @return string 
     */
    protected function camelize($str)
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    /**
     * fetches table columns
     * @return array
     */
    protected function fetchColumns()
    {
        $stmt = $this->db->query('DESCRIBE ' . $this->getTableName());

        $columns = [];

        while ($row = $stmt->fetch()) {
            $columns[] = $row['Field'];
        }

        return $columns;
    }

    /**
     * can be overridden. By default, Entity classname is [DAOParentNamespace]\Entity\EntityName
     * @return string
     */
    protected function getEntityClassName()
    {
        if (!$this->entityClassName) {
            $className = get_class($this);
            $segments = explode('\\', $className);
            $entityName = str_replace(array('Dao', 'DAO'), '', array_pop($segments));
            array_pop($segments);
            $this->entityClassName = implode('\\', $segments) . '\\Entity\\' . $entityName;
        }

        return $this->entityClassName;
    }

    /**
     * builds Entity from assoc array. Can be overriden.
     * @param array $data
     * @return \SimpleDAO\entityClassName
     */
    protected function buildEntity(array $data)
    {
        $entityClassName = $this->getEntityClassName();

        $entity = new $entityClassName();

        foreach ($data as $field => $value) {
            if (method_exists($entity, $methodName = 'set' . $this->camelize($field))) {
                $entity->$methodName($value);
            }
        }

        if (($hasOnes = $this->getHasOne())) {
            foreach ($hasOnes as $name => $hasOne) {
                $dao = $this->getDAO($hasOne['daoClass'], $hasOne['options']);
                $filteredData = [];
                foreach ($data as $field => $value) {

                    if (strpos($field, $name . '__') !== FALSE) {
                        $filteredData[str_replace($name . '__', '', $field)] = $value;
                    }
                }
                $compositeEntity = $dao->buildEntity($filteredData);
                $entity->{'set' . $this->camelize($name)}($compositeEntity);
            }
        }

        return $entity;
    }

    /**
     * Get an entity array from a PDOStatement
     * @param \PDOStatement $query
     */
    public function getEntities($query)
    {
        $query->execute();

        $data = $query->fetchAll();

        if (!$data) {
            return $data;
        }

        $entities = [];

        foreach ($data as $value) {
            $entities[] = $this->buildEntity($value);
        }

        return $entities;
    }

    /**
     * Get one entity from a PDOStatement
     * @param \PDOStatement $query
     */
    public function getEntity($query)
    {
        $query->execute();

        $data = $query->fetch();

        $query->closeCursor();

        if (!$data) {
            return $data;
        }

        return $this->buildEntity($data);
    }

    /**
     * Finds an entity using the pk param
     * @param integer $id
     * @return object entity
     */
    public function find($id)
    {
        return $this->findOne(array("{$this->getTableName()}.$this->primary = ? " => $id));
    }

    /**
     * Fetches an array of entity based on assoc array criteria, with optional limit/offset
     * @param array $criteria
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function findMany(array $criteria = array(), $limit = null, $offset = null)
    {
        return $this->getEntities($this->prepareSelect($criteria, $limit, $offset));
    }

    /**
     * Fetches one entity based on assoc array criteria
     * @param array $criteria
     * @return entity
     */
    public function findOne(array $criteria = array())
    {
        return $this->getEntity($this->prepareSelect($criteria, 1));
    }

    /**
     * Prepares and executes query with PDO. It's based on assoc array of criterias
     * with optional limit/offset
     * @param type $criteria
     * @param type $limit
     * @param type $offset
     * @return type
     */
    protected function prepareSelect($criteria = array(), $limit = null, $offset = null, $order = "")
    {

        $query = $this->db->prepare($this->buildSQL($criteria, $limit, $offset, $order));

        $i = 1;
        if (is_array($criteria) && $criteria) {
            foreach ($criteria as $val) {
                $query->bindValue($i++, $val);
            }
        }

        if ($limit && $offset) {
            $query->bindValue($i++, $limit, \PDO::PARAM_INT);
            $query->bindValue($i++, $limit, \PDO::PARAM_INT);
        } else if ($limit) {
            $query->bindValue($i++, $limit, \PDO::PARAM_INT);
        }

        return $query;
    }

    protected function generateColumns($prefix = "", &$tables = [])
    {
        $tableName = $this->getTableName();
        
        $countTables = count($tables);
        
        $tableAlias = "joined_$countTables";
        
        $columns = implode(',', array_map(function($col) use($tableAlias, $prefix){
            return "$tableAlias.`$col` as `$prefix$col`";
        }, $this->getTableColumns()));
        
        if (array_search($this->getTableName(), $tables)) {
            return $columns;
        }
        $tables[] = $this->getTableName();
        if (($hasOnes = $this->getHasOne())) {

            foreach ($hasOnes as $name => $hasOne) {
                $dao = $this->getDAO($hasOne['daoClass'], $hasOne['options']);
                $columns .= ',' . $dao->generateColumns($prefix.$name.'__', $tables);
            }
        }
        return $columns;
    }

    protected function generateJoins($fromAlias, &$joins, &$tables = [])
    {
        if( ! $tables){
            $tables[$fromAlias] = $this->getTableName();
        }
        if (($hasOnes = $this->getHasOne())) {
            foreach ($hasOnes as $name => $hasOne) {
                $dao = $this->getDAO($hasOne['daoClass'], $hasOne['options']);
                
                $daoTable = $dao->getTableName();

                $countTables = count($tables);

                $daoAlias = "joined_$countTables";
                
                $joins[] = "LEFT JOIN `{$dao->getTableName()}` $daoAlias ON $fromAlias.`{$hasOne['from']}` = $daoAlias.`{$hasOne['to']}`";
                
                if(array_search($daoTable, $tables) === FALSE){
                    $tables[$daoAlias] = $daoTable;
                    $dao->generateJoins($daoAlias, $joins, $tables);
                }
            }
        }
    }

    protected function buildSQL($criteria = array(), $limit = null, $offset = null, $order = "")
    {
        $tableAlias = 'joined_0';
        $columns = $this->generateColumns();

        $sql = "SELECT $columns FROM `" . $this->getTableName() . "` as $tableAlias";
        
        $joins = [];
        
        $tables = [];
        
        $this->generateJoins($tableAlias, $joins, $tables);

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', $joins);
        }
        

        if (is_array($criteria) && $criteria) {
            $criteriaTransformed = [];

            foreach($criteria as $col => $value) {
                $colTransformed = $col;
                foreach ($tables as $alias => $name) {
                    if(strpos($col, $name.'.') !== FALSE) {
                        $colTransformed = str_replace($name.'.', $alias.'.', $col);
                    }
                }
                $criteriaTransformed[$colTransformed] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', array_keys($criteriaTransformed));
        }


        if ($limit && $offset) {
            $sql .= ' LIMIT ?, ?';
        } else if ($limit) {
            $sql .= ' LIMIT ?';
        }

        $sql .= $order;

        return $sql;
    }

    /**
     * Persist an entity in database. If entity has a pk value, it will be updated.
     * Otherwise, it will be inserted.
     * @param object $entity
     * @return integer
     */
    public function save($entity)
    {
        $idGetter = 'get' . $this->camelize($this->primary);

        if (method_exists($entity, $idGetter)) {
            $id = $entity->$idGetter();
        }

        if (isset($id)) {
            return $this->update($entity);
        } else {
            return $this->insert($entity);
        }
    }

    /**
     * Inserts a new entity in database.
     * @param object $entity
     * @return integer
     */
    public function insert($entity)
    {
        if (($hasOnes = $this->getHasOne())) {
            foreach ($hasOnes as $name => $hasOne) {
                $dao = $this->getDAO($hasOne['daoClass'], $hasOne['options']);
                $compositeEntity = $entity->{'get' . $this->camelize($name)}();

                if ($compositeEntity && !$dao->save($compositeEntity)) {
                    return 0;
                }
            }
        }

        $columns = $this->getTableColumns();

        $sql = 'INSERT INTO ' . $this->getTableName() . ' (';

        $data = [];

        foreach ($columns as $col) {
            $getterName = 'get' . $this->camelize($col);
            if (method_exists($entity, $getterName)) {
                $data["`$col`"] = $entity->$getterName();
            } else {
                continue;
            }
        }

        if (!$data) {
            return 0;
        }

        $sql .= implode(',', array_keys($data)) . ') VALUES (';

        foreach ($data as $value) {
            $sql .= '?,';
        }

        $sql = trim($sql, ',') . ')';

        $query = $this->db->prepare($sql);

        $i = 1;

        foreach ($data as $value) {
            $query->bindValue($i++, $value);
        }

        $res = $query->execute();

        $idSetter = 'set' . $this->camelize($this->primary);

        if (method_exists($entity, $idSetter)) {
            $entity->$idSetter($this->db->lastInsertId());
        }

        return $res;
    }

    /**
     * Updates an entity in database, using its pk value
     * @param object $entity
     * @return int
     */
    public function update($entity)
    {

        if (($hasOnes = $this->getHasOne())) {
            foreach ($hasOnes as $name => $hasOne) {
                $dao = $this->getDAO($hasOne['daoClass'], $hasOne['options']);
                $compositeEntity = $entity->{'get' . $this->camelize($name)}();

                if ($compositeEntity && !$dao->save($compositeEntity)) {
                    return 0;
                }
            }
        }
        $columns = $this->getTableColumns();

        $sql = 'UPDATE ' . $this->getTableName() . ' SET ';

        $where = "WHERE `{$this->primary}` = ?";

        $data = [];

        foreach ($columns as $col) {
            if ($col == $this->primary) {
                continue;
            }
            $getterName = 'get' . $this->camelize($col);
            if (method_exists($entity, $getterName)) {
                $data[$col] = $entity->$getterName();
            } else {
                continue;
            }
        }

        if (!$data) {
            return 0;
        }

        $values = [];

        foreach ($data as $col => $value) {
            $values[] = '`' . $col . '` = ?';
        }

        $sql .= implode(', ', $values) . ' ' . $where;

        $query = $this->db->prepare($sql);

        $i = 1;

        foreach ($data as $value) {
            $query->bindValue($i++, $value);
        }

        $idGetter = 'get' . $this->camelize($this->primary);

        if (method_exists($entity, $idGetter)) {
            $id = $entity->$idGetter();
        } else {
            return 0;
        }

        $query->bindValue($i++, $id);

        return $query->execute();
    }

    public function delete($entity)
    {
        $sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `' . $this->primary . '` = ?';
        $query = $this->db->prepare($sql);

        $idGetter = 'get' . $this->camelize($this->primary);

        if (method_exists($entity, $idGetter)) {
            $id = $entity->$idGetter();
        } else {
            return 0;
        }

        $query->bindValue(1, $id);

        return $query->execute();
    }

    private function parseHasOne()
    {
        $hasOne = $this->hasOne;

        if (!$hasOne) {
            return;
        }

        $unset = [];

        foreach ($hasOne as $name => $options) {
            if (is_string($options)) {
                $unset[] = $name;
                $name = $options;
                $options = [];
            }

            if (empty($options['daoClass'])) {
                $options['daoClass'] = $this->guessDaoClass($name);
            }
            if (empty($options['from'])) {
                $options['from'] = $name . '_id';
            }
            if (empty($options['to'])) {
                $options['to'] = 'id';
            }
            if (empty($options['options'])) {
                $options['options'] = [];
            }

            $hasOne[$name] = $options;
        }

        foreach ($unset as $u) {
            unset($hasOne[$u]);
        }

        $this->hasOne = $hasOne;
    }

    protected function guessDaoClass($entityName)
    {
        $classNameSegements = explode('\\', get_class($this));

        array_pop($classNameSegements);

        return implode('\\', $classNameSegements) . '\\' . $entityName . 'DAO';
    }

    /**
     * 
     * @param string $daoClass
     * @param array $options
     * @return DAO
     */
    protected function getDAO($daoClass, $options = null)
    {
        if (!isset(self::$_DAO_CACHE[spl_object_hash($this->db)][$daoClass])) {
            $dao = new $daoClass($this->db, $options);
            self::$_DAO_CACHE[spl_object_hash($this->db)][$daoClass] = $dao;
        }

        return self::$_DAO_CACHE[spl_object_hash($this->db)][$daoClass];
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    protected function setDb(\PDO $db)
    {
        $this->db = $db;
    }

    protected function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    public function getTableColumns()
    {
        if (!$this->tableColumns) {
            $this->tableColumns = $this->fetchColumns();
        }
        return $this->tableColumns;
    }

    function getHasOne()
    {
        if (!$this->_hasOneParsed) {
            $this->parseHasOne();
            $this->_hasOneParsed = true;
        }
        return $this->hasOne;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

}
