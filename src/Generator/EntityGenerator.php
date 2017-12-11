<?php

namespace SimpleDAO\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use SimpleDAO\Utils\Inflector;

/**
 * Description of EntityGenerator
 *
 * @author cevantime
 */
class EntityGenerator extends DBCodeGenerator
{

    protected static $TYPE_MAPPING = [
        'varchar' => 'string',
        'text' => 'string',
        'int' => 'int',
        'bigint' => 'int',
        'mediumint' => 'int',
        'smallint' => 'int',
        'tinyint' => 'bool',
        'boolean' => 'bool',
        'decimal' => 'string',
        'float' => 'string',
        'double' => 'string',
        'real' => 'string',
        'bit' => 'string',
        'serial' => 'string',
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'time' => 'string',
        'year' => 'int',
        'char' => 'string',
        'mediumtext' => 'string',
        'tinytext' => 'string',
        'longtext' => 'string',
        'blob' => 'string',
        'tinyblob' => 'string',
        'mediumblob' => 'string',
        'tinyblob' => 'string',
        'longblob' => 'string',
        'binary' => 'string',
        'varbinary' => 'string',
        'enum' => 'string',
        'set' => 'string',
    ];

    /**
     * 
     * @return ClassType
     */
    public function generate()
    {
        $namespace = new PhpNamespace(ltrim($this->getOption('namespace') . '\Entity', '\\'));

        $entityName = Inflector::camelize($this->getTable());
        $generator = new ClassType($entityName, $namespace);

        $fieldInfosStmt = $this->getDb()->prepare('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');

        $fieldInfosStmt->bindValue(1, $this->getOption('dbname'));
        $fieldInfosStmt->bindValue(2, $this->getTable());

        $fieldInfosStmt->execute();

        $fieldInfos = $fieldInfosStmt->fetchAll();

        $hasM = $this->getOption('_hasMany');
        $hasMany =& $hasM;
        
        $hasO = $this->getOption('_hasOne');
        $hasOne =& $hasO;
        
        if( !empty($hasOne[$this->getTable()])) {
            
            if (count($hasOne[$this->getTable()]) === 2 && count($hasOne[$this->getTable()]) === count($fieldInfos)) {
                for ($i = 0; $i < count($hasOne[$hasOne[$this->getTable()][0]['table']]); $i++){
                    if($hasOne[$hasOne[$this->getTable()][0]['table']][$i]['table'] === $this->getTable()){
                        unset($hasOne[$hasOne[$this->getTable()][0]['table']][$i]);
                    }
                }
                for ($i = 0; $i < count($hasOne[$hasOne[$this->getTable()][1]['table']]); $i++){
                    if($hasOne[$hasOne[$this->getTable()][1]['table']][$i]['table'] === $this->getTable()){
                        unset($hasOne[$hasOne[$this->getTable()][1]['table']][$i]);
                    }
                }
                $hasMany[$hasOne[$this->getTable()][0]['table']][] = [
                    "through" => $this->getTable(),
                    "from" => $hasOne[$this->getTable()][0],
                    "to" => $hasOne[$this->getTable()][1],
                    "table" => $hasOne[$this->getTable()][1]['table']
                ];

                $hasMany[$hasOne[$this->getTable()][1]['table']][] = [
                    "through" => $this->getTable(),
                    "from" => $hasOne[$this->getTable()][1],
                    "to" => $hasOne[$this->getTable()][0],
                    'table' => $hasOne[$this->getTable()][0]['table']
                ];

                return null;
            }
        }

        foreach ($fieldInfos as $fieldInfo) {
            $name = null;
            if( ! empty($hasOne[$this->getTable()])) {
                $continue = false;
                foreach ($hasOne[$this->getTable()] as $ho) {
                    if ($ho['from'] === $fieldInfo['COLUMN_NAME']) {
                        $continue = true;
                    }
                }
                if($continue) {
                    continue;
                }
            }
            
            if (!isset($name)) {
                $name = lcfirst(Inflector::camelize($fieldInfo['COLUMN_NAME']));
                $type = self::$TYPE_MAPPING[$fieldInfo['DATA_TYPE']];
            }
            

            $generator->addProperty($name)
                ->addComment("$name of the $entityName")
                ->addComment("@var $type $name")
                ->setVisibility('private');
            

            $getter = $generator->addMethod('get' . ucfirst($name))
                ->setVisibility('public')
                ->addBody('return $this->' . $name . ';');

            $setter = $generator->addMethod('set' . ucfirst($name))
                ->setVisibility('public')
                ->addBody('$this->' . $name . ' = $' . $name . ';');

            $parameter = $setter->addParameter($name);

            if (strnatcmp(phpversion(), '7.0.0') >= 0) {
                $parameter->setTypeHint($type);
            }
        }

        return $generator;
    }

}
