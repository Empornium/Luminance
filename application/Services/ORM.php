<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Core\Entity;
use Luminance\Errors\InternalError;
use Luminance\Services\DB\LegacyWrapper;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

class ORM extends Service {

    protected $db;

    protected static $useServices = [
        'db' => 'DB',
    ];

    public function load($entity_class, $ID) {
        $cls = "Luminance\\Entities\\{$entity_class}";
        $table = $cls::$table;
        $pkey_column = $cls::get_pkey_property();
        $sql = "SELECT * FROM `{$table}` WHERE `{$pkey_column}` = ?";
        $stmt = $this->db->raw_query($sql, [$ID]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($res) {
            $entity = $cls::create_from_db($res);
            return $entity;
        } else {
            return null;
        }
    }

    public function get($entity_class, $where, $params = null) {
        $cls = "Luminance\\Entities\\{$entity_class}";
        $table = $cls::$table;
        $where_clause = $this->get_where_clause($where);
        $sql = "SELECT * FROM `{$table}` {$where_clause}";
        $stmt = $this->db->raw_query($sql, $params);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($res) {
            $entity = $cls::create_from_db($res);
            return $entity;
        } else {
            return null;
        }
    }

    public function find($entity_class, $where = null, $params = null) {
        $cls = "Luminance\\Entities\\{$entity_class}";
        $table = $cls::$table;
        $where_clause = $this->get_where_clause($where);
        $sql = "SELECT * FROM `{$table}` {$where_clause}";
        $stmt = $this->db->raw_query($sql, $params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $objects = [];
        foreach ($results as $result) {
            $object = $cls::create_from_db($result);
            $objects[] = $object;
        }
        return $objects;
    }

    public function find_count($entity_class, $where = null, $params = null) {
        $cls = "Luminance\\Entities\\{$entity_class}";
        $table = $cls::$table;
        $where_clause = $this->get_where_clause($where);
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM `{$table}` {$where_clause}";
        $stmt = $this->db->raw_query($sql, $params);
        $count = $this->db->found_rows();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $objects = [];
        foreach ($results as $result) {
            $object = $cls::create_from_db($result);
            $objects[] = $object;
        }
        $result = [$objects, $count];
        return $result;
    }

    protected function get_where_clause($where = null) {
        $where_clause = ($where) ? "WHERE {$where}" : '';
        return $where_clause;
    }

    public function save(Entity $entity) {
        if ($entity->exists_in_db()) {
            $entity = $this->update($entity);
        } else {
            // Default logic for Created columns
            //if ($entity-> && is_null($entity->Created)) {
            //$entity->Created = new \DateTime();
            //}
            $entity = $this->insert($entity);
        }
        return $entity;
    }

    public function insert(Entity $entity) {
        $table = $entity->get_table();
        $values = $entity->get_unsaved_values();

        $columns = [];
        $param_names = [];
        $param_values = [];
        foreach ($values as $name => $value) {
            $columns[] = "`{$name}`";
            $param_name = ":{$name}";
            $param_names[] = $param_name;
            $param_values[$param_name] = $value;
        }

        $sql = "INSERT INTO `{$table}` (".implode(',', $columns).") VALUES (".implode(',', $param_names).")";
        $this->db->raw_query($sql, $param_values);

        $auto_increment_column = $entity->get_auto_increment_column();
        if ($auto_increment_column) {
            $values[$auto_increment_column] = $this->db->last_insert_id();
        }

        $entity->set_saved_values($values);
        return $entity;
    }

    public function update(Entity $entity) {
        $table = $entity->get_table();
        $values = $entity->get_unsaved_values();
        $pkey_value = $entity->get_pkey_value();

        if (count($values) == 0) {
            return $entity;
        }

        $column_clauses = [];
        $param_values = [];
        foreach ($values as $name => $value) {
            $column_clauses[] = "`{$name}` = :{$name}";
            $param_values[":{$name}"] = $value;
        }

        $pkey_column = $entity->get_pkey_property();
        $param_values[':_pkey_'] = $pkey_value;

        $sql = "UPDATE `{$table}` SET ".implode(', ', $column_clauses)." WHERE `{$pkey_column}` = :_pkey_";
        $this->db->raw_query($sql, $param_values);

        $entity->set_saved_values($values);
        return $entity;
    }

    public function delete(Entity $entity) {
        $table = $entity->get_table();
        $pkey_value = $entity->get_pkey_value();
        $pkey_column = $entity->get_pkey_property();
        $param_values[':_pkey_'] = $pkey_value;
        $sql = "DELETE FROM `{$table}` WHERE `{$pkey_column}` = :_pkey_";
        return $this->db->raw_query($sql, $param_values);
    }

    # Various rarely used DDL functions below:


    public function get_tables() {
        $table_info = $this->db->raw_query("SHOW TABLES;")->fetchAll(\PDO::FETCH_NUM);
        $tables = [];
        foreach ($table_info as $t) {
            $tables[] = $t[0];
        }
        return $tables;
    }

    protected function get_entity_classes() {
        # We have to work around the autoloader to ensure all Entity classes are actually loaded
        $entity_dir = $this->master->application_path.'/Entities';
        $entity_files = scandir($entity_dir);
        foreach ($entity_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                require_once($entity_dir.'/'.$file);
            }
        }

        $entity_classes = [];
        foreach (get_declared_classes() as $cls) {
            if (is_subclass_of($cls, 'Luminance\Core\Entity')) {
                $entity_classes[] = $cls;
            }
        }
        return $entity_classes;
    }

    public function update_tables($sql = null) {
        $entity_classes = $this->get_entity_classes();
        $tables = $this->get_tables();
        $this->db->raw_query('SET FOREIGN_KEY_CHECKS=0');

        if ($sql) {
            $vars = $this->parse_sql($sql);
            if (in_array($vars['table'], $tables)) {
                $this->update_table($vars);
            } else {
                $this->create_table($vars);
            }
        } else {
            foreach ($entity_classes as $cls) {
                $vars = [];
                $vars['table'] = $cls::get_table();
                $vars['properties'] = $cls::get_properties();
                $vars['indexes'] = $cls::get_indexes();
                $vars['attributes'] = $cls::get_attributes();
                if (in_array($vars['table'], $tables)) {
                    $this->update_table($vars);
                } else {
                    $this->create_table($vars);
                }
            }
        }
    }

    public function drop_tables() {
        $this->db->raw_query('SET FOREIGN_KEY_CHECKS=0');
        $tables = $this->get_tables();
        foreach ($tables as $table) {
            $this->drop_table($table);
        }
    }

    protected function get_table_specification($table) {
        $tableSpec = $this->db->raw_query("SHOW CREATE TABLE {$table}")->fetch(\PDO::FETCH_ASSOC);
        return $this->parse_sql($tableSpec['Create Table']);
    }

    protected function get_index_parameters($indexes) {
        $return_indexes = [];
        foreach ($indexes as $index) {
            if (is_array($index)) {
                if (array_key_exists('length', $index)) {
                    $return_indexes[] = $index;
                } elseif (array_key_exists('name', $index)) {
                    $return_indexes[] = $index['name'];
                }
            } else {
                $return_indexes[] = $index;
            }
        }
        return $return_indexes;
    }

    protected function parse_sql($sql) {
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

                $vars['properties'][$property->name] = $var;
            }
            if (!is_null($property->key)) {
                switch ($property->key->type) {
                    case 'PRIMARY KEY':
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->get_index_parameters($property->key->columns),
                            'type'    => 'primary',
                        ];
                        break;
                    case 'FOREIGN KEY':
                        $actions = [];
                        foreach ($property->references->options->options as $action) {
                            $actions[] = "{$action['name']} {$action['value']}";
                        }

                        $vars['indexes'][$property->name] = [
                            'columns' => $this->get_index_parameters($property->key->columns),
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
                            'columns' => $this->get_index_parameters($property->key->columns),
                            'type'    => 'unique',
                        ];
                        break;
                    case 'FULLTEXT KEY':
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->get_index_parameters($property->key->columns),
                            'type'    => 'fulltext',
                        ];
                        break;
                    default:
                        $vars['indexes'][$property->key->name] = [
                            'columns' => $this->get_index_parameters($property->key->columns),
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

    protected function get_column_sql($name, $options) {
        $nullable       = (array_key_exists('nullable', $options)) ? $options['nullable'] : true;
        $auto_increment = (array_key_exists('auto_increment', $options)) ? $options['auto_increment'] : false;
        $unsigned       = (array_key_exists('unsigned', $options)) ? $options['unsigned'] : false;
        $zerofill       = (array_key_exists('zerofill', $options)) ? $options['zerofill'] : false;
        $length         = (array_key_exists('length', $options)) ? $options['length'] : null;
        $default        = (array_key_exists('default', $options)) ? $options['default'] : null;
        if (array_key_exists('sqltype', $options)) {
            $sqltype = $options['sqltype'];
        } else {
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
        }
        $col_sql = "`{$name}` {$sqltype}";
        if ($unsigned | $zerofill) {
            $col_sql .= ' UNSIGNED';
        }
        if ($zerofill) {
            $col_sql .= ' ZEROFILL';
        }
        if (!is_null($default)) {
            $col_sql .= " DEFAULT {$default}";
        }
        if ($nullable) {
            $col_sql .= ' NULL';
        } else {
            $col_sql .= ' NOT NULL';
        }
        if ($auto_increment) {
            $col_sql .= ' AUTO_INCREMENT';
        }
        return $col_sql;
    }

    protected function get_index_sql($name, $options) {
        $column_strings = [];
        foreach ($options['columns'] as $column) {
            if (is_array($column)) {
                $column_strings[] = "`{$column['name']}`({$column['length']})";
            } else {
                $column_strings[] = "`{$column}`";
            }
        }
        if (array_key_exists('type', $options)) {
            switch ($options['type']) {
                case 'primary':
                    $index_sql = 'PRIMARY KEY ';
                    break;
                case 'foreign':
                    $index_sql  = "CONSTRAINT `{$name}` FOREIGN KEY ";
                    $index_sql .= '(' . implode(',', $column_strings) . ') REFERENCES ';
                    foreach ($options['references']['columns'] as $column) {
                        $reference_columns[] = "`{$column}`";
                    }
                    $index_sql .= "`{$options['references']['table']}` (" . implode(',', $reference_columns) . ")";
                    foreach ($options['actions'] as $action) {
                        $index_sql .= " {$action}";
                    }
                    return $index_sql;
                  break;
                case 'unique':
                    $index_sql = "UNIQUE KEY `{$name}` ";
                    break;
                case 'fulltext':
                    $index_sql = "FULLTEXT KEY `{$name}` ";
                    break;
                default:
                    $index_sql = "INDEX `{$name}` ";
            }
        } else {
            $index_sql = "INDEX `{$name}` ";
        }
        $index_sql .= '(' . implode(',', $column_strings) . ')';
        return $index_sql;
    }

    protected function get_storage_engines() {
        $indexed_engines = $this->db->raw_query("SHOW ENGINES")->fetchAll();
        $indexed_engines = array_column($indexed_engines, 'Engine');
        foreach ($indexed_engines as $index => $engine) {
            $engines[strtolower($engine)] = $engine;
        }
        return $engines;
    }

    protected function get_attribute_sql($name, $option) {
        switch ($name) {
            case 'engine':
                $engines = $this->get_storage_engines();
                $engine = (array_key_exists($option, $engines)) ? $engines[$option] : 'InnoDB';
                return "Engine={$engine} ";
                break;
        }
    }

    protected function get_table_columns($table) {
        $column_info = $this->master->db->raw_query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_NUM);
        $columns = [];
        foreach ($column_info as $c) {
            $columns[] = $c[0];
        }
        return $columns;
    }

    protected function get_table_indexes($table) {
        $index_info = $this->get_table_specification($table);
        $indexes = [];
        foreach ($index_info['indexes'] as $name => $i) {
            if (@$i['type'] == 'primary') continue;
            if (!in_array($name, $indexes)) {
                $indexes[] = $name;
            }
        }
        return $indexes;
    }

    protected function get_primary_index($table) {
        $index_info = $this->get_table_specification($table);
        $indexes = [];
        foreach ($index_info['indexes'] as $name => $i) {
            if (@$i['type'] == 'primary') {
                return $i['columns'];
            }
        }
        return false;
    }

    protected function create_table($vars) {
        print("Creating table {$vars['table']}\n");
        $sql = "CREATE TABLE `{$vars['table']}` (\n";
        $parts = [];
        $primary_cols = [];
        foreach ($vars['properties'] as $property => $options) {
            $primary = (array_key_exists('primary', $options)) ? $options['primary'] : false;
            if ($primary) {
                $options['nullable'] = false;
                $primary_cols[] = "`{$property}`";
            }
            $col_sql = $this->get_column_sql($property, $options);
            $parts[] = $col_sql;
        }
        if (count($primary_cols)) {
            $parts[] = "PRIMARY KEY (".implode(',', $primary_cols).")";
        }
        foreach ($vars['indexes'] as $index => $options) {
            $index_sql = $this->get_index_sql($index, $options);
            $parts[] = $index_sql;
        }
        $sql .= implode(",\n", $parts);
        $sql .= "\n)";
        foreach ($vars['attributes'] as $attribute => $options) {
            $sql .= ' '.$this->get_attribute_sql($attribute, $options);
        }
        $sql .= "DEFAULT CHARSET=utf8\n";
        $this->db->raw_query($sql);
    }

    protected function update_table($vars) {
        # TODO: Detect unneeded columns (deleting automatically is too risky)
        # TODO: Detect storage engine change
        $columns   = $this->get_table_columns($vars['table']);
        $tableSpec = $this->get_table_specification($vars['table']);
        $last_column = null;
        $sql = null;
        foreach ($vars['properties'] as $property => $options) {
            # Column doesn't exist yet
            if (!in_array($property, $columns)) {
                $col_sql = $this->get_column_sql($property, $options);
                $after_sql = (is_null($last_column)) ? 'FIRST' : "AFTER `{$last_column}`";
                $sql[] = "ADD COLUMN {$col_sql} {$after_sql}";

            # Detect column changes and update if necessary
            } else {
                $altered = false;

                # Check sqltype
                if (array_key_exists('sqltype', $options)) {
                    # We always do string comparisons to avoid things like int(10) != string("'10'")
                    if (trim((string)$tableSpec['properties'][$property]['sqltype'], "'") != trim((string)$options['sqltype'], "'")) {
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
                        if (preg_match('/\([\d]+\)/', $options['sqltype']) == 0) {
                            $test = preg_replace('/\([\d]+\)/', '', $tableSpec['properties'][$property]['sqltype']);
                        } else {
                            $test = $tableSpec['properties'][$property]['sqltype'];
                        }

                        # Column really was changed!
                        if (trim((string)$test, "'") != trim((string)$options['sqltype'], "'")) {
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
                    } elseif (trim((string)$tableSpec['properties'][$property]['default'], "'") != trim((string)$options['default'], "'")) {
                        $tableSpec['properties'][$property]['default'] = $options['default'];
                        $altered = true;
                    }
                }

                # Check nullable
                if (!array_key_exists('nullable', $options)) {
                    $options['nullable'] = !array_key_exists('auto_increment', $options);
                }
                if ($tableSpec['properties'][$property]['nullable'] != $options['nullable']) {
                    $tableSpec['properties'][$property]['nullable'] = $options['nullable'];
                    $altered = true;
                }

                if ($altered) {
                    $col_sql = $this->get_column_sql($property, $tableSpec['properties'][$property]);
                    $sql[] = "MODIFY COLUMN {$col_sql}";
                }
            }
            $last_column = $property;
        }

        $indexes = $this->get_table_indexes($vars['table']);

        # Add new index
        foreach ($vars['indexes'] as $index => $options) {
            if (!in_array($index, $indexes)) {
                if (@$options['type'] == 'primary') continue;
                $index_sql = $this->get_index_sql($index, $options);
                $sql[] = "ADD {$index_sql}";
            } else {
                unset($indexes[array_search($index, $indexes)]);
            }
        }

        # Drop removed index
        foreach ($indexes as $index) {
            $sql[] = "DROP INDEX `{$index}`";
        }

        # Handle primary index
        #TODO handle multi-column primaries
        $primary = $this->get_primary_index($vars['table']);
        foreach ($vars['properties'] as $property => $options) {
            if (@$options['primary'] !== true) {
                continue;
            } else {
                if ([$property] !== $primary) {
                    $sql[] = "DROP PRIMARY KEY";
                    $sql[] = "ADD PRIMARY KEY (`{$property}`)";
                }
            }
        }

        # Single atomic update for each table, should be quicker
        if (is_array($sql)) {
            print("Upgrading table {$vars['table']}\n");
            $sql = "ALTER TABLE `{$vars['table']}` " . implode(', ', $sql);
            try {
                $this->db->raw_query($sql);
            } catch (\PDOException $e) {
                var_dump($sql);
                print($e->getMessage());
                die();
            }
        }
    }

    protected function drop_table($table) {
        print("Deleting table {$table}\n");
        $this->db->raw_query("DROP TABLE {$table}");
    }
}
