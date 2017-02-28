<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Entity;
use Luminance\Errors\InternalError;
use Luminance\Services\DB\LegacyWrapper;

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


    protected function get_tables() {
        $table_info = $this->master->db->raw_query("SHOW TABLES;")->fetchAll(\PDO::FETCH_NUM);
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

    public function update_tables() {
        $entity_classes = $this->get_entity_classes();
        $tables = $this->get_tables();

        foreach ($entity_classes as $cls) {
            $vars = [];
            $vars['table'] = $cls::get_table();
            $vars['properties'] = $cls::get_properties();
            $vars['indexes'] = $cls::get_indexes();
            if (in_array($vars['table'], $tables)) {
                $this->update_table($vars);
            } else {
                $this->create_table($vars);
            }
        }
    }

    protected function get_column_sql($name, $options) {
        $nullable = (array_key_exists('nullable', $options)) ? $options['nullable'] : true;
        $auto_increment = (array_key_exists('auto_increment', $options)) ? $options['auto_increment'] : false;
        $length = (array_key_exists('length', $options)) ? $options['length'] : null;
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
        $index_sql = "`{$name}` ";
        $column_strings = [];
        foreach ($options['columns'] as $column) {
            $column_strings[] = "`{$column}`";
        }
        $index_sql .= '(' . implode(',', $column_strings) . ')';
        return $index_sql;
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
        $index_info = $this->master->db->raw_query("SHOW INDEXES FROM `{$table}`")->fetchAll(\PDO::FETCH_NUM);
        $indexes = [];
        foreach ($index_info as $i) {
            $name = $i[2];
            if ($name == 'PRIMARY') {
                continue;
            }
            if (!in_array($name, $indexes)) {
                $indexes[] = $name;
            }
        }
        return $indexes;
    }

    protected function create_table($vars) {
        print("Creating table {$vars['table']}\n");
        $sql = "CREATE TABLE `{$vars['table']}` (";
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
            $parts[] = 'INDEX ' . $index_sql;
        }
        $sql .= implode(', ', $parts);
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
        $this->master->db->raw_query($sql);
    }

    protected function update_table($vars) {
        # TODO: Detect unneeded columns (deleting automatically is too risky)
        # TODO: Detect columns with changed spec (altering automatically is too risky)
        $columns = $this->get_table_columns($vars['table']);
        $last_column = null;
        foreach ($vars['properties'] as $property => $options) {
            if (!in_array($property, $columns)) {
                print("Table {$vars['table']}: Adding column {$property}\n");
                $col_sql = $this->get_column_sql($property, $options);
                $after_sql = (is_null($last_column)) ? 'FIRST' : "AFTER `{$last_column}`";
                $sql = "ALTER TABLE `{$vars['table']}` ADD COLUMN {$col_sql} {$after_sql}";
                $this->master->db->raw_query($sql);
            }
            $last_column = $property;
        }

        $indexes = $this->get_table_indexes($vars['table']);
        foreach ($vars['indexes'] as $index => $options) {
            if (!in_array($index, $indexes)) {
                print("Table {$vars['table']}: Adding index {$index}\n");
                $index_sql = $this->get_index_sql($index, $options);
                $sql = "ALTER TABLE `{$vars['table']}` ADD INDEX {$index_sql}";
                $this->master->db->raw_query($sql);
            }
        }
    }

}
