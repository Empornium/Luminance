<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Core\Entity;

use Luminance\Errors\SystemError;
use Luminance\Errors\InternalError;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

class ORM extends Service {

    protected $db;
    protected $tableSpecs = [];

    protected static $useServices = [
        'db' => 'DB',
    ];

    public function load($entityClass, $ID) {
        $cls = "Luminance\\Entities\\{$entityClass}";
        $table = $cls::$table;
        $pKeyColumns = $cls::getPKeyProperties();
        foreach ($pKeyColumns as &$pKeyColumn) {
            $pKeyColumn = "`{$pKeyColumn}` = ?";
        }
        $pKeyColumns = implode(' AND ', $pKeyColumns);
        $sql = "SELECT * FROM `{$table}` WHERE {$pKeyColumns}";

        # Ensure keys are in a numerically indexed array
        if (!is_array($ID)) $ID = [$ID];
        $ID = array_values($ID);

        $stmt = $this->db->rawQuery($sql, $ID);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!empty($res)) {
            $entity = $cls::createFromDB($res);
            return $entity;
        } else {
            return null;
        }
    }

    public function get($entityClass, $where, array $params = null) {
        $cls = "Luminance\\Entities\\{$entityClass}";
        $table = $cls::$table;
        $whereClause = $this->getWhereClause($where);
        $pKeyColumns = implode(',', $cls::getPKeyProperties());

        if (is_null($pKeyColumns)) {
            throw new InternalError("No primary key in table {$table}");
        }

        $sql = "SELECT {$pKeyColumns} FROM `{$table}` {$whereClause}";
        $stmt = $this->db->rawQuery($sql, $params);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!empty($res)) {
            return $res;
        } else {
            return null;
        }
    }

    public function updateOrCreate($entityClass, array $where, array $updates) {
        $sql = [];
        $params = [];
        foreach ($where as $column => $value) {
            $sql[] = "{$column} = ?";
            $params[] = $value;
        }
        $sql = implode(" AND ", $sql);
        $res = $this->get($entityClass, $sql, $params);
        if (empty($res)) {
            $res = $this->create($entityClass, $updates);
        } else {
            $updates = array_merge($where, $updates);
            foreach ($updates as $column => $value) {
                $res->$column = $value;
            }
            $res = $this->save($res);
        }
        return $res;
    }

    public function create($entityClass, array $tuples) {
        $cls = "Luminance\\Entities\\{$entityClass}";
        $entity = new $cls;
        foreach ($tuples as $key => $value) {
            $entity->$key = $value;
        }
        $res = $this->save($entity);
        return $res;
    }

    public function find($entityClass, $where = null, array $params = null, $order = null, $limit = null) {
        $cls = "Luminance\\Entities\\{$entityClass}";
        $table = $cls::$table;
        $whereClause = $this->getWhereClause($where);
        $orderClause = $this->getOrderClause($order);
        $limitClause = $this->getLimitClause($limit);
        $pKeyColumns = implode(',', $cls::getPKeyProperties());
        if (is_null($pKeyColumns)) throw new InternalError("No primary key in table {$table}");
        $sql = "SELECT {$pKeyColumns} FROM `{$table}` {$whereClause} {$orderClause} {$limitClause}";
        $stmt = $this->db->rawQuery($sql, $params);
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($res)) {
            return $res;
        } else {
            return null;
        }
    }

    public function findCount($entityClass, $where = null, array $params = null, $order = null, $limit = null) {
        $cls = "Luminance\\Entities\\{$entityClass}";
        $table = $cls::$table;
        $whereClause = $this->getWhereClause($where);
        $orderClause = $this->getOrderClause($order);
        $limitClause = $this->getLimitClause($limit);
        $pKeyColumns = implode(',', $cls::getPKeyProperties());
        if (is_null($pKeyColumns)) throw new InternalError("No primary key in table {$table}");
        $sql = "SELECT SQL_CALC_FOUND_ROWS {$pKeyColumns} FROM `{$table}` {$whereClause} {$orderClause} {$limitClause}";
        $stmt = $this->db->rawQuery($sql, $params);
        $count = $this->db->foundRows();
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($res)) {
            return [$res, $count];
        } else {
            return [null, 0];
        }
    }

    protected function getWhereClause($where = null) {
        $whereClause = !empty($where) ? "WHERE {$where}" : '';
        return $whereClause;
    }

    protected function getOrderClause($order = null) {
        $orderClause = !empty($order) ? "ORDER BY {$order}" : '';
        return $orderClause;
    }

    protected function getLimitClause($limit = null) {
        $limitClause = !empty($limit) ? "LIMIT {$limit}" : '';
        return $limitClause;
    }

    public function save(Entity $entity, $allowUpdate = false) {
        if ($entity->existsInDB()) {
            $entity = $this->update($entity);
        } else {
            $entity = $this->insert($entity, $allowUpdate);
        }
        return $entity;
    }

    public function insert(Entity $entity, $allowUpdate = false) {
        $table = $entity->getTable();
        $values = $entity->getUnsavedValues();

        $columns = [];
        $parameterNames = [];
        $parameterValues = [];
        foreach ($values as $name => $value) {
            $columns[] = "`{$name}`";
            $parameterName = ":{$name}";
            $parameterNames[] = $parameterName;
            $parameterValues[$parameterName] = $value;
        }

        $sql = "INSERT INTO `{$table}` (".implode(',', $columns).") VALUES (".implode(',', $parameterNames).")";
        if ($allowUpdate === true) {
            $sql .= " ON DUPLICATE KEY UPDATE ";
            $updates = [];
            foreach ($values as $name => $value) {
                $updates[] = "`{$name}` = :{$name}_update";
                $parameterValues[":{$name}_update"] = $value;
            }
            $sql .= implode(', ', $updates);
        }
        $this->db->rawQuery($sql, $parameterValues);

        $autoIncrementColumn = $entity->getAutoIncrementColumn();
        if (!empty($autoIncrementColumn)) {
            $values[$autoIncrementColumn] = $this->db->lastInsertID();
        }

        $entity->setSavedValues($values);
        return $entity;
    }

    public function update(Entity $entity) {
        $table = $entity->getTable();
        $values = $entity->getUnsavedValues();
        $pKeyValues = $entity->getPKeyValues();

        if (count($values) === 0) {
            return $entity;
        }

        $columnClauses = [];
        $parameterValues = [];
        foreach ($values as $name => $value) {
            $columnClauses[] = "`{$name}` = :{$name}";
            $parameterValues[":{$name}"] = $value;
        }

        $pKeyColumns = $entity->getPKeyProperties();
        foreach ($pKeyValues as $index => $pKeyValue) {
            $parameterValues[":_pkey{$index}_"] = $pKeyValue;
        }

        foreach ($pKeyColumns as $index => $pKeyColumn) {
            $where[] = "`{$pKeyColumn}` = :_pkey{$index}_";
        }

        $columnClauses = implode(', ', $columnClauses);
        $where = implode(' AND ', $where);

        $sql = "UPDATE `{$table}` SET {$columnClauses} WHERE {$where}";
        $this->db->rawQuery($sql, $parameterValues);

        $entity->setSavedValues($values);
        return $entity;
    }

    public function delete(Entity $entity) {
        $table = $entity->getTable();
        $pKeyValues = $entity->getPKeyValues();
        $pKeyColumns = $entity->getPKeyProperties();
        foreach ($pKeyValues as $index => $pKeyValue) {
            $parameterValues[":_pkey{$index}_"] = $pKeyValue;
        }
        foreach ($pKeyColumns as $index => $pKeyColumn) {
            $where[] = "`{$pKeyColumn}` = :_pkey{$index}_";
        }
        $where = implode(' AND ', $where);
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return $this->db->rawQuery($sql, $parameterValues);
    }

    # Various rarely used DDL functions below:


    public function getTables() {
        $tableInfo = $this->db->rawQuery("SHOW TABLES;")->fetchAll(\PDO::FETCH_NUM);
        $tables = [];
        foreach ($tableInfo as $t) {
            $tables[] = $t[0];
        }
        return $tables;
    }

    public function getEntityClasses() {
        # We have to work around the autoloader to ensure all Entity classes are actually loaded
        $entityDir = $this->master->applicationPath.'/Entities';
        $entityFiles = scandir($entityDir);
        foreach ($entityFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                require_once($entityDir.'/'.$file);
            }
        }

        $entityClasses = [];
        foreach (get_declared_classes() as $cls) {
            if (is_subclass_of($cls, 'Luminance\Core\Entity')) {
                $entityClasses[] = $cls;
            }
        }
        return $entityClasses;
    }

    public function updateTables($sql = null) {
        $entityClasses = $this->getEntityClasses();
        $tables = $this->getTables();
        $this->db->rawQuery('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($sql)) {
            $vars = $this->parseSQL($sql);
            if (in_array($vars['table'], $tables)) {
                $this->updateTable($vars);
            } else {
                $this->createTable($vars);
            }
        } else {
            foreach ($entityClasses as $cls) {
                $vars = [];
                $vars['table'] = $cls::getTable();
                $vars['properties'] = $cls::getProperties();
                $vars['indexes'] = $cls::getIndexes();
                $vars['attributes'] = $cls::getAttributes();
                if (in_array($vars['table'], $tables)) {
                    $this->updateTable($vars);
                } else {
                    $this->createTable($vars);
                }
            }
        }
    }

    public function pruneTables($sql = null) {
        $entityClasses = $this->getEntityClasses();
        $tables = $this->getTables();
        $this->db->rawQuery('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($sql)) {
            $vars = $this->parseSQL($sql);
            if (in_array($vars['table'], $tables)) {
                $this->pruneTable($vars);
            }
        } else {
            foreach ($entityClasses as $cls) {
                $vars = [];
                $vars['table'] = $cls::getTable();
                $vars['properties'] = $cls::getProperties();
                $vars['indexes'] = $cls::getIndexes();
                $vars['attributes'] = $cls::getAttributes();
                if (in_array($vars['table'], $tables)) {
                    $this->pruneTable($vars);
                }
            }
        }
    }

    public function dropTables() {
        $this->db->rawQuery('SET FOREIGN_KEY_CHECKS=0');
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $this->dropTable($table);
        }
    }

    public function getTableSpecification($table) {
        try {
            $sql = "SHOW CREATE TABLE {$table}";
            $tableSpec = $this->db->rawQuery($sql)->fetch(\PDO::FETCH_ASSOC);
            if (!array_key_exists($table, $this->tableSpecs)) {
                $this->tableSpecs[$table] = $this->parseSQL($tableSpec['Create Table']);
            }
            return $this->tableSpecs[$table];
        } catch (\PDOException $e) {
            print($sql.PHP_EOL);
            print($e->getMessage().PHP_EOL);
            die();
        }
    }

    protected function getIndexParameters($indexes) {
        $returnIndexes = [];
        foreach ($indexes as $index) {
            if (is_array($index)) {
                if (array_key_exists('length', $index)) {
                    $returnIndexes[] = $index;
                } elseif (array_key_exists('name', $index)) {
                    $returnIndexes[] = $index['name'];
                }
            } else {
                $returnIndexes[] = $index;
            }
        }
        return $returnIndexes;
    }

    protected function parseSQL($sql) {
        # We use the phpMyAdmin library and the raw SQL to create an ORM
        # entity definition on the fly, it's cleaner this way I think.
        $parser = new Parser($sql);
        $sql = null;

        # Search for the table definition
        foreach ($parser->statements as $statement) {
            if ($statement instanceof CreateStatement) {
                $sql = $statement;
                break;
            }
        }

        # Cleanup some memory
        unset($parser);

        # Process the table into an entity
        $vars['table'] = $sql->name->table;
        $vars['properties'] = [];
        $vars['indexes']    = [];
        $vars['attributes'] = [];
        foreach ($sql->fields as $property) {
            if (!is_null($property->type)) {
                $var = [];

                switch ($property->type->name) {
                    case 'INT':
                    case 'BIGINT':
                    case 'TINYINT':
                        $var['type'] = 'int';
                        break;
                    case 'FLOAT':
                    case 'DOUBLE':
                        $var['type'] = 'float';
                        break;
                    case 'VARCHAR':
                    case 'VARBINARY':
                    case 'TEXT':
                    case 'MEDIUMTEXT':
                    case 'ENUM':
                    case 'BLOB':
                        $var['type'] = 'str';
                        break;
                    case 'DATETIME':
                        $var['type'] = 'timestamp';
                        break;
                }

                $var['sqltype'] = $property->type->name;
                if (!empty($property->type->parameters)) {
                    $var['sqltype'] .= '('.implode(',', $property->type->parameters).')';
                }

                foreach ($property->options->options as $option) {
                    if (is_array($option)) {
                        $name = $option['name'];
                    } else {
                        $name = $option;
                    }

                    switch (strtolower($name)) {
                        case 'unsigned':
                            $var['unsigned'] = true;
                            break;
                        case 'zerofill':
                            $var['zerofill'] = true;
                            break;
                        case 'default':
                            $var['default'] = $option['value'];
                            break;
                        case 'not null':
                            $var['nullable'] = false;
                            break;
                        case 'auto_increment':
                            $var['auto_increment'] = true;
                            break;
                    }
                }

                # Fixup nulls
                if (!array_key_exists('nullable', $var)) {
                    $var['nullable'] = !array_key_exists('auto_increment', $var);
                }

                if (array_key_exists('nullable', $var) && array_key_exists('default', $var)) {
                    if ($var['nullable'] === false && $var['default'] === null) {
                        unset($var['default']);
                    }
                }

                $vars['properties'][$property->name] = $var;
            }
            if (!is_null($property->key)) {
                switch ($property->key->type) {
                    case 'PRIMARY KEY':
                        foreach ($this->getIndexParameters($property->key->columns) as $column) {
                            if (!array_key_exists($column, $vars['properties'])) {
                                throw new InternalError("Primary key column ({$column}) does not exist in table {$vars['table']}");
                            } else {
                                $vars['properties'][$column]['primary'] = true;
                            }
                        }
                        break;
                    case 'FOREIGN KEY':
                        $actions = [];
                        foreach ($property->references->options->options as $action) {
                            $actions[] = "{$action['name']} {$action['value']}";
                        }

                        $vars['indexes'][$property->name] = [
                            'columns' => $this->getIndexParameters($property->key->columns),
                            'type'       => 'foreign',
                            'references' => [
                                'table'    => $property->references->table->table,
                                'columns'  => $property->references->columns,
                            ],
                            'actions'    => $actions,
                        ];
                        break;
                    case 'UNIQUE KEY':
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->getIndexParameters($property->key->columns),
                            'type'    => 'unique',
                        ];
                        break;
                    case 'FULLTEXT KEY':
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->getIndexParameters($property->key->columns),
                            'type'    => 'fulltext',
                        ];
                        break;
                    default:
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->getIndexParameters($property->key->columns),
                        ];
                }
            }
        }
        foreach ($sql->entityOptions->options as $attribute) {
            switch (strtolower($attribute['name'])) {
                case 'engine':
                    $vars['attributes']['engine'] = $attribute['value'];
                    break;
            }
        }
        return $vars;
    }

    public function getColumnSQL($name, $options) {
        $nullable       = $options['nullable']       ?? true;
        $autoIncrement  = $options['auto_increment'] ?? false;
        $unsigned       = $options['unsigned']       ?? false;
        $zerofill       = $options['zerofill']       ?? false;
        $length         = $options['length']         ?? null;
        $sqltype        = $options['sqltype']        ?? null;
        $type           = $options['type']           ?? null;
        if (empty($sqltype)) {
            if (!empty($type)) {
                switch ($options['type']) {
                    case 'int':
                        if (is_null($length)) {
                            $length = 11;
                        }
                        $sqltype = "INT({$length})";
                        break;
                    case 'str':
                        if (is_null($length)) {
                            $length = 255;
                        }
                        $sqltype = "VARCHAR({$length})";
                        break;
                    case 'bool':
                        $sqltype = 'TINYINT(1)';
                        break;
                    case 'timestamp':
                        $sqltype = 'TIMESTAMP DEFAULT 0'; # this avoids MySQL's defaults, which are too specific
                        break;
                }
            } else {
                throw new SystemError("Unknown column type for {$name}");
            }
        }
        $columnSQL = "`{$name}` {$sqltype}";
        if ($unsigned | $zerofill) {
            $columnSQL .= ' UNSIGNED';
        }
        if ($zerofill === true) {
            $columnSQL .= ' ZEROFILL';
        }
        if (array_key_exists('default', $options)) {
            $default = $options['default'];
            if (is_null($default)) {
                $default = "NULL";
            }
            if (empty($default)) {
                $default = "'{$default}'";
            }
            $columnSQL .= " DEFAULT {$default}";
        }
        if ($nullable === true) {
            $columnSQL .= ' NULL';
        } else {
            $columnSQL .= ' NOT NULL';
        }
        if ($autoIncrement === true) {
            $columnSQL .= ' AUTO_INCREMENT';
        }
        return $columnSQL;
    }

    protected function getIndexSQL($name, $options) {
        $columnStrings = [];
        foreach ($options['columns'] as $column) {
            if (is_array($column)) {
                $columnStrings[] = "`{$column['name']}`({$column['length']})";
            } else {
                $columnStrings[] = "`{$column}`";
            }
        }
        if (array_key_exists('type', $options)) {
            switch ($options['type']) {
                case 'primary':
                    $indexSQL = 'PRIMARY KEY ';
                    break;
                case 'foreign':
                    $indexSQL  = "CONSTRAINT `{$name}` FOREIGN KEY ";
                    $indexSQL .= '(' . implode(',', $columnStrings) . ') REFERENCES ';
                    foreach ($options['references']['columns'] as $column) {
                        $referenceColumns[] = "`{$column}`";
                    }
                    $indexSQL .= "`{$options['references']['table']}` (" . implode(',', $referenceColumns) . ")";
                    foreach ($options['actions'] as $action) {
                        $indexSQL .= " {$action}";
                    }
                    return $indexSQL;
                  break;
                case 'unique':
                    $indexSQL = "UNIQUE KEY `{$name}` ";
                    break;
                case 'fulltext':
                    $indexSQL = "FULLTEXT KEY `{$name}` ";
                    break;
                default:
                    $indexSQL = "INDEX `{$name}` ";
            }
        } else {
            $indexSQL = "INDEX `{$name}` ";
        }
        $indexSQL .= '(' . implode(',', $columnStrings) . ')';
        return $indexSQL;
    }

    protected function getStorageEngines() {
        $indexedEngines = $this->db->rawQuery("SHOW ENGINES")->fetchAll();
        $indexedEngines = array_column($indexedEngines, 'Engine');
        foreach ($indexedEngines as $index => $engine) {
            $engines[strtolower($engine)] = $engine;
        }
        return $engines;
    }

    protected function getAttributeSQL($name, $option) {
        switch ($name) {
            case 'engine':
                $engines = $this->getStorageEngines();
                $engine = (array_key_exists($option, $engines)) ? $engines[$option] : 'InnoDB';
                return "Engine={$engine} \n";
                break;

            case 'charset':
                return "CHARSET={$option} \n";
                break;
            case 'collate':
                return "COLLATE={$option} \n";
                break;
        }
    }

    public function getTableColumns($table) {
        $columnInfo = $this->master->db->rawQuery("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_NUM);
        $columns = [];
        foreach ($columnInfo as $c) {
            $columns[] = $c[0];
        }
        return $columns;
    }

    protected function getTableAutocolumns($table) {
        $autoInfo = $this->getTableSpecification($table);
        $autos = [];
        foreach ($autoInfo['properties'] as $name => $i) {
            if (@$i['auto_increment'] === true) {
                $autos[$name] = $i;
            }
        }
        return $autos;
    }

    protected function getTableIndexes($table) {
        $indexInfo = $this->getTableSpecification($table);
        $indexes = [];
        foreach ($indexInfo['indexes'] as $name => $i) {
            if (@$i['type'] === 'primary') continue;
            if (!in_array($name, $indexes)) {
                $indexes[] = $name;
            }
        }
        return $indexes;
    }

    protected function getPrimaryIndexes($table) {
        $indexInfo = $this->getTableSpecification($table);
        $primaries = [];
        foreach ($indexInfo['properties'] as $name => $i) {
            if (@$i['primary'] === true) {
                $primaries[] = $name;
            }
        }
        return $primaries;
    }

    protected function createTable($vars) {
        print("Creating table {$vars['table']}".PHP_EOL);
        $sql = "";
        $parts = [];
        $primaryColumns = [];
        foreach ($vars['properties'] as $property => $options) {
            $primary = (array_key_exists('primary', $options)) ? $options['primary'] : false;
            if ($primary === true) {
                $options['nullable'] = false;
                $primaryColumns[] = "`{$property}`";
            }
            $columnSQL = $this->getColumnSQL($property, $options);
            $parts[] = $columnSQL;
        }
        if (count($primaryColumns)) {
            $parts[] = "PRIMARY KEY (".implode(',', $primaryColumns).")";
        }
        foreach ($vars['indexes'] as $index => $options) {
            $indexSQL = $this->getIndexSQL($index, $options);
            $parts[] = $indexSQL;
        }
        $sql .= implode(",\n", $parts);
        $sql = "CREATE TABLE `{$vars['table']}` (\n{$sql}\n)";

        $defaultAttributes =  [
            'engine'    => 'InnoDB',
            'charset'   => 'utf8mb4',
            'collate'   => 'utf8mb4_general_ci',
        ];

        $attributes = array_merge($defaultAttributes, $vars['attributes']);
        foreach ($attributes as $attribute => $options) {
            $sql .= ' '.$this->getAttributeSQL($attribute, $options);
        }

        try {
            $this->db->rawQuery($sql);
        } catch (\PDOException $e) {
            print($sql.PHP_EOL);
            print($e->getMessage().PHP_EOL);
            die();
        }
    }

    private function safeStringCompare($a, $b) {
        # Handle null values
        $a = (is_null($a)) ? 'NULL' : $a;
        $b = (is_null($b)) ? 'NULL' : $b;

        # Explicit cast to string
        $a=trim((string)$a, "'");
        $b=trim((string)$b, "'");
        return $a === $b;
    }

    protected function updateTable($vars) {
        $columns   = $this->getTableColumns($vars['table']);
        $tableSpec = $this->getTableSpecification($vars['table']);
        $lastColumn = null;
        $sql = null;
        foreach ($vars['properties'] as $property => $options) {
            # Column doesn't exist yet
            if (!in_array($property, $columns)) {
                $columnSQL = $this->getColumnSQL($property, $options);
                $afterSQL = (is_null($lastColumn)) ? 'FIRST' : "AFTER `{$lastColumn}`";
                $sql[] = "ADD COLUMN {$columnSQL} {$afterSQL}";

            # Detect column changes and update if necessary
            } else {
                $altered = false;

                # Check sqltype
                if (array_key_exists('sqltype', $options)) {
                    # We always do string comparisons to avoid things like int(10) != string("'10'")
                    if ($this->safeStringCompare($tableSpec['properties'][$property]['sqltype'], $options['sqltype']) === false) {
                        # regex hack to handle unsigned types
                        if (preg_match('/unsigned/i', $options['sqltype'])) {
                            $options['sqltype'] = preg_replace('/ unsigned/i', '', $options['sqltype']);
                            $options['unsigned'] = true;
                        }

                        # regex hack to handle zerofill types
                        if (preg_match('/zerofill/i', $options['sqltype'])) {
                            $options['sqltype'] = preg_replace('/ zerofill/i', '', $options['sqltype']);
                            $options['zerofill'] = true;
                        }

                        # Tidy up enum types
                        $options['sqltype'] = preg_replace('/( )?,( )?/', ',', $options['sqltype']);

                        # regex hack to detect sqltypes without sizes
                        if (preg_match('/\([\d]+\)/', $options['sqltype']) === 0) {
                            $test = preg_replace('/\([\d]+\)/', '', $tableSpec['properties'][$property]['sqltype']);
                        } else {
                            $test = $tableSpec['properties'][$property]['sqltype'];
                        }

                        # Column really was changed!
                        if ($this->safeStringCompare($test, $options['sqltype']) === false) {
                            $tableSpec['properties'][$property]['sqltype'] = $options['sqltype'];
                            $altered = true;
                        }
                    }
                }

                # Check default
                if (array_key_exists('default', $options)) {
                    if (!array_key_exists('default', $tableSpec['properties'][$property])) {
                        $tableSpec['properties'][$property]['default'] = $options['default'];
                        $altered = true;
                    } elseif ($this->safeStringCompare($tableSpec['properties'][$property]['default'], $options['default']) === false) {
                        $tableSpec['properties'][$property]['default'] = $options['default'];
                        $altered = true;
                    }
                } else {
                    unset($tableSpec['properties'][$property]['default']);
                }

                # Check nullable
                if (!array_key_exists('nullable', $options)) {
                    # Cannot be null if primary or auto-increment
                    $autoIncrement = $options['auto_increment'] ?? false;
                    $primary        = $options['primary']        ?? false;
                    $options['nullable'] = !($autoIncrement || $primary);
                }
                if (!($tableSpec['properties'][$property]['nullable'] === $options['nullable'])) {
                    $tableSpec['properties'][$property]['nullable'] = $options['nullable'];
                    $altered = true;
                }

                if ($altered === true) {
                    $columnSQL = $this->getColumnSQL($property, $tableSpec['properties'][$property]);
                    $sql[] = "MODIFY COLUMN {$columnSQL}";
                }
            }
            $lastColumn = $property;
        }

        # Grab auto-columns of installed schema
        $autos = $this->getTableAutocolumns($vars['table']);
        $indexes = $vars['indexes'];

        # Ensure auto-columns will have an index
        foreach ($autos as $auto => $options) {
            $exists = false;
            if (array_key_exists($auto, $vars['properties'])) {
                $autoColumn = $vars['properties'][$auto];
                if (array_key_exists('primary', $autoColumn)) {
                    if ($autoColumn['primary'] === true) {
                        continue;
                    }
                }
            }

            # Uh-oh, the column got removed, check to see if we have an index for it
            foreach ($indexes as $index) {
                if (in_array($auto, $index['columns'])) {
                    $exists = true;
                }
            }

            # Nope, no index, we better add one.
            if ($exists === false) {
                $vars['indexes']['AUTO'] = [ 'columns' => [ $auto ] ];
            }
        }

        $indexes = $this->getTableIndexes($vars['table']);

        # Add new index
        foreach ($vars['indexes'] as $index => $options) {
            if (!in_array($index, $indexes)) {
                if (@$options['type'] === 'primary') continue;
                $indexSQL = $this->getIndexSQL($index, $options);
                $sql[] = "ADD {$indexSQL}";
            } else {
                unset($indexes[array_search($index, $indexes)]);
            }
        }

        # Drop removed index
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql[] = "DROP INDEX `{$index}`";
            }
        }

        # Handle primary index
        $oldPrimaries = $this->getPrimaryIndexes($vars['table']);
        $newPrimaries = [];
        foreach ($vars['properties'] as $property => $options) {
            if (!(@$options['primary'] === true)) {
                continue;
            } else {
                $newPrimaries[] = $property;
            }
        }

        if (!empty(array_diff($newPrimaries, $oldPrimaries)) ||
            !empty(array_diff($newPrimaries, $oldPrimaries))) {
            if (!empty($oldPrimaries)) {
                $sql[] = "DROP PRIMARY KEY";
            }
            $primaryKeys = [];
            foreach ($newPrimaries as $newPrimary) {
                $primaryKeys[] = "`{$newPrimary}`";
            }
            $primaryKeys = implode(', ', $primaryKeys);
            $sql[] = "ADD PRIMARY KEY ({$primaryKeys})";
        }

        # Handle engine changes
        if (array_key_exists('engine', $vars['attributes'])) {
            $newEngine = strtolower($vars['attributes']['engine']);
            $oldEngine = strtolower($tableSpec['attributes']['engine']);
            $availableEngines = $this->getStorageEngines();

            # Only submit the change if the new engine is available
            if (array_key_exists($newEngine, $availableEngines)) {
                if (!$this->safeStringCompare($newEngine, $oldEngine)) {
                    # Lookup the exact name
                    $newEngine = $availableEngines[$newEngine];
                    $sql[] = "ENGINE = {$newEngine}";
                }
            }
        }

        # Single atomic update for each table, should be quicker
        if (is_array($sql)) {
            print("Upgrading table {$vars['table']}".PHP_EOL);
            $sql = "ALTER TABLE `{$vars['table']}` " . implode(', ', $sql);
            try {
                $this->db->rawQuery($sql);
            } catch (\PDOException $e) {
                print($sql.PHP_EOL);
                print($e->getMessage().PHP_EOL);
                die();
            }
        }

        foreach ($columns as $column) {
            if (!in_array($column, array_keys($vars['properties']))) {
                print("Extra column found in {$vars['table']}: {$column}".PHP_EOL);
            }
        }
    }

    protected function pruneTable($vars) {
        $columns   = $this->getTableColumns($vars['table']);
        $sql = null;
        foreach ($columns as $column) {
            if (!in_array($column, array_keys($vars['properties']))) {
                $sql[] = "DROP COLUMN `{$column}`";
            }
        }

        # Single atomic update for each table, should be quicker
        if (is_array($sql)) {
            print("Pruning table {$vars['table']}".PHP_EOL);
            $sql = "ALTER TABLE `{$vars['table']}` " . implode(', ', $sql);
            try {
                $this->db->rawQuery($sql);
            } catch (\PDOException $e) {
                print($sql.PHP_EOL);
                print($e->getMessage().PHP_EOL);
                die();
            }
        }
    }

    protected function dropTable($table) {
        print("Deleting table {$table}".PHP_EOL);
        $this->db->rawQuery("DROP TABLE {$table}");
    }
}
