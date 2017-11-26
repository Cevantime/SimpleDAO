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
     * @param \PDO $db
     * @param string $tableName
     */
    public function __construct(\PDO $db, $tableName = null)
    {
        $this->db = $db;

        if (null === $tableName) {
            $tableName = $this->guessTableName();
        }

        $this->setTableName($tableName);
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

        return $entity;
    }

    /**
     * Finds an entity using the pk param
     * @param integer $id
     * @return object entity
     */
    public function find($id)
    {
        return $this->findOne(array($this->primary . ' = ? ' => $id));
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
        $query = $this->prepareSelect($criteria, $limit, $offset);

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
     * Fetches one entity based on assoc array criteria
     * @param array $criteria
     * @return entity
     */
    public function findOne(array $criteria = array())
    {
        $query = $this->prepareSelect($criteria, 1);

        $data = $query->fetch();

        $query->closeCursor();

        if (!$data) {
            return $data;
        }

        return $this->buildEntity($data);
    }

    /**
     * Prepares and executes query with PDO. It's based on assoc array of criterias
     * with optional limit/offset
     * @param type $criteria
     * @param type $limit
     * @param type $offset
     * @return type
     */
    protected function prepareSelect($criteria = array(), $limit = null, $offset = null)
    {
        $sql = 'SELECT * FROM ' . $this->getTableName();

        if (is_array($criteria) && $criteria) {
            $sql .= ' WHERE ' . implode(' AND ', array_keys($criteria));
        }


        if ($limit && $offset) {
            $sql .= ' LIMIT ?, ?';
        } else if ($limit) {
            $sql .= ' LIMIT ?';
        }

        $query = $this->db->prepare($sql);

        $i = 1;
        if (is_array($criteria) && $criteria) {
            foreach ($criteria as $val) {
                $query->bindParam($i++, $val);
            }
        }

        if ($limit && $offset) {
            $query->bindParam($i++, $limit, \PDO::PARAM_INT);
            $query->bindParam($i++, $limit, \PDO::PARAM_INT);
        } else if ($limit) {
            $query->bindParam($i++, $limit, \PDO::PARAM_INT);
        }

        $query->execute();

        return $query;
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
        $columns = $this->getTableColumns();

        $sql = 'INSERT INTO ' . $this->getTableName() . ' (';

        $data = [];

        foreach ($columns as $col) {
            $getterName = 'get' . $this->camelize($col);
            if (method_exists($entity, $getterName)) {
                $data[$col] = $entity->$getterName();
            } else {
                continue;
            }
        }

        if (!$data) {
            return;
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
        $columns = $this->getTableColumns();

        $sql = 'UPDATE ' . $this->getTableName() . ' SET ';

        $where = "WHERE {$this->primary} = ?";

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
            return;
        }

        $values = [];

        foreach ($data as $col => $value) {
            $values[] = $col . ' = ?';
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

}
