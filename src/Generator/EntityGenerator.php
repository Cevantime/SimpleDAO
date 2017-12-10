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

    public function generate()
    {
        $namespace = new PhpNamespace(ltrim($this->getOption('namespace') . '\Entity', '\\'));

        $generator = new ClassType(Inflector::camelize($this->getTable()), $namespace);

        $fieldInfosStmt = $this->getDb()->prepare('SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS'
                . ' WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');

        $fieldInfosStmt->bindValue(1, $this->getOption('dbname'));
        $fieldInfosStmt->bindValue(2, $this->getTable());

        $fieldInfosStmt->execute();

        $fieldInfos = $fieldInfosStmt->fetchAll();

        $constraintInfosStmt = $this->getDb()->prepare("SELECT REFERENCED_TABLE_NAME, FOR_COL_NAME, REF_COL_NAME "
                . "FROM INFORMATION_SCHEMA.`REFERENTIAL_CONSTRAINTS`,INFORMATION_SCHEMA.INNODB_SYS_FOREIGN_COLS "
                . "WHERE CONSTRAINT_SCHEMA = :dbname "
                . "AND TABLE_NAME = :table "
                . "AND CONCAT(INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.`CONSTRAINT_SCHEMA`,'/',INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.`CONSTRAINT_NAME` ) = INFORMATION_SCHEMA.INNODB_SYS_FOREIGN_COLS.ID");

        $constraintInfosStmt->bindValue(':dbname', $this->getOption('dbname'));
        $constraintInfosStmt->bindValue(':table', $this->getTable());
        
        $constraintInfosStmt->execute();

        $constraintInfos = $constraintInfosStmt->fetchAll();
        
        var_dump($constraintInfos);
        
        foreach ($fieldInfos as $fieldInfo) {
            $name = null;
            
            foreach ($constraintInfos as $cInfo) {
                if ($cInfo['FOR_COL_NAME'] === $fieldInfo['COLUMN_NAME']) {
                    $type = $this->getOption('namespace') . '\\Entity\\'.Inflector::camelize($cInfo['REFERENCED_TABLE_NAME']);
                    $name = lcfirst(Inflector::camelize($cInfo['REFERENCED_TABLE_NAME']));
                    break;
                }
            }
            
            if( ! isset($name)) {
                $name = lcfirst(Inflector::camelize($fieldInfo['COLUMN_NAME']));
                $type = self::$TYPE_MAPPING[$fieldInfo['DATA_TYPE']];
            }
            $generator->addProperty($name)
                    ->addComment("")
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

        return "<?php\n\n" . $namespace . $generator->__toString();
    }

}
