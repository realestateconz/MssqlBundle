<?php

namespace Realestate\MssqlBundle\Schema;

/**
 * Schema manager for the MsSql/Dblib RDBMS.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Scott Morken <scott.morken@pcmail.maricopa.edu>
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @version     $Revision$
 * @since       2.0
 */

class DblibSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableViewDefinition($view)
    {
        return new View($view['TABLE_NAME'], $view['VIEW_DEFINITION']);
    }

    protected function _getPortableTableDefinition($table)
    {
        return array_shift($table);
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        foreach($tableIndexes AS $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            if($v['is_primary_key'] == true) {
                $v['primary'] = true;
            } else {
                $v['primary'] = false;
            }
            if ($v['is_unique'] == true) {
                $v['non_unique'] = false;
            }
            else {
                $v['non_unique'] = true;
            }
            $tableIndexes[$k] = $v;
        }

        return parent::_getPortableTableIndexesList($tableIndexes, $tableName);
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return end($sequence);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['Database'];
    }

    /**
     * Gets a portable column definition.
     *
     * The database type is mapped to a corresponding Doctrine mapping type.
     *
     * @param $tableColumn
     * @return array
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = \array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['data_type']);

        $type = array();
        $length = $unsigned = $fixed = null;
        if ( ! empty($tableColumn['character_maximum_length'])) {
            $length = $tableColumn['character_maximum_length'];
            if (stristr($tableColumn['character_maximum_length'], 'null') !== false) {
                $tableColumn['character_maximum_length'] = null;
            }
        }

        if ( ! isset($tableColumn['column_name'])) {
            $tableColumn['column_name'] = '';
        }

        if (stristr($tableColumn['column_default'], 'null') !== false) {
            $tableColumn['column_default'] = null;
        }

        $precision = null;
        $scale = null;

        switch ($dbType) {
            case 'integer':
            case 'bigint':
            case 'tinyint':
            case 'smallint':
            case 'numeric':
                if($tableColumn['numeric_scale'] > 0) {
                    $type = 'decimal';
                    $precision = $tableColumn['numeric_precision'];
                    $scale = $tableColumn['numeric_scale'];
                } else {
                    $type = 'integer';
                }
                $length = null;
                break;
            case 'bit':
                $type = 'boolean';
                $length = null;
                break;
            case 'varchar':
                $fixed = false;
            case 'nvarchar':
                $fixed = false;
            case 'char':
            case 'text':
                $fixed = false;
            case 'ntext':
                $fixed = false;
            case 'nchar':
                if ($length == '1' && preg_match('/^(is|has)/', $tableColumn['column_name'])) {
                    $type = 'boolean';
                } else {
                    $type = 'string';
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                break;
            case 'datetime':
            case 'timestamp':
            case 'smalldatetime':
                $type = 'datetime';
                $length = null;
                break;
            case 'float':
                $precision = $tableColumn['numeric_precision'];
                $scale = $tableColumn['numeric_scale'];
                $type = 'decimal';
                $length = null;
                break;
            case 'clob':
            case 'nclob':
                $length = null;
                $type = 'text';
                break;
            case 'binary':
            case 'varbinary':
            case 'image':
                $type = 'blob';
                $length = null;
            break;
            case 'uniqueidentifier':
                $type = 'uniqueidentifier';
                $length = null;
            break;
            default:
                $type = 'string';
                $length = null;
        }

        $options = array(
            'notnull'    => (bool) ($tableColumn['is_nullable'] === 'NO'),
            'fixed'      => (bool) $fixed,
            'unsigned'   => (bool) $unsigned,
            'default'    => $tableColumn['column_default'],
            'length'     => $length,
            'precision'  => $precision,
            'scale'      => $scale,
            'platformDetails' => array(),
        );

        return new Column($tableColumn['column_name'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

     protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = array();
        foreach ($tableForeignKeys as $key => $value) {
            $value = \array_change_key_case($value, CASE_LOWER);
            if (!isset($list[$value['constraint_name']])) {
                if ($value['delete_rule'] == "NO ACTION") {
                    $value['delete_rule'] = null;
                }

                $list[$value['pkconstraint_name']] = array(
                    'name' => $value['pkconstraint_name'],
                    'local' => array(),
                    'foreign' => array(),
                    'foreignTable' => $value['fktable_name'],
                    'onDelete' => $value['delete_rule'],
                );
            }
            $list[$value['pkconstraint_name']]['local'][$value['deferrability']] = $value['pkcolumn_name'];
            $list[$value['pkconstraint_name']]['foreign'][$value['deferrability']] = $value['fkcolumn_name'];
        }

        $result = array();
        foreach($list AS $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']), $constraint['foreignTable'],
                array_values($constraint['foreign']),  $constraint['name'],
                array('onDelete' => $constraint['onDelete'])
            );
        }

        return $result;
    }

    public function createDatabase($name)
    {
        $query = "CREATE DATABASE $name";
        if ($this->_conn->options['database_device']) {
            $query.= ' ON '.$this->_conn->options['database_device'];
            $query.= $this->_conn->options['database_size'] ? '=' .
                     $this->_conn->options['database_size'] : '';
        }
        return $this->_conn->standaloneQuery($query, null, true);
    }
    /**
     * alter an existing table
     *
     * @param string $name         name of the table that is intended to be changed.
     * @param array $changes     associative array that contains the details of each type
     *                             of change that is intended to be performed. The types of
     *                             changes that are currently supported are defined as follows:
     *
     *                             name
     *
     *                                New name for the table.
     *
     *                            add
     *
     *                                Associative array with the names of fields to be added as
     *                                 indexes of the array. The value of each entry of the array
     *                                 should be set to another associative array with the properties
     *                                 of the fields to be added. The properties of the fields should
     *                                 be the same as defined by the Metabase parser.
     *
     *
     *                            remove
     *
     *                                Associative array with the names of fields to be removed as indexes
     *                                 of the array. Currently the values assigned to each entry are ignored.
     *                                 An empty array should be used for future compatibility.
     *
     *                            rename
     *
     *                                Associative array with the names of fields to be renamed as indexes
     *                                 of the array. The value of each entry of the array should be set to
     *                                 another associative array with the entry named name with the new
     *                                 field name and the entry named Declaration that is expected to contain
     *                                 the portion of the field declaration already in DBMS specific SQL code
     *                                 as it is used in the CREATE TABLE statement.
     *
     *                            change
     *
     *                                Associative array with the names of the fields to be changed as indexes
     *                                 of the array. Keep in mind that if it is intended to change either the
     *                                 name of a field and any other properties, the change array entries
     *                                 should have the new names of the fields as array indexes.
     *
     *                                The value of each entry of the array should be set to another associative
     *                                 array with the properties of the fields to that are meant to be changed as
     *                                 array entries. These entries should be assigned to the new values of the
     *                                 respective properties. The properties of the fields should be the same
     *                                 as defined by the Metabase parser.
     *
     *                            Example
     *                                array(
     *                                    'name' => 'userlist',
     *                                    'add' => array(
     *                                        'quota' => array(
     *                                            'type' => 'integer',
     *                                            'unsigned' => 1
     *                                        )
     *                                    ),
     *                                    'remove' => array(
     *                                        'file_limit' => array(),
     *                                        'time_limit' => array()
     *                                    ),
     *                                    'change' => array(
     *                                        'name' => array(
     *                                            'length' => '20',
     *                                            'definition' => array(
     *                                                'type' => 'text',
     *                                                'length' => 20,
     *                                            ),
     *                                        )
     *                                    ),
     *                                    'rename' => array(
     *                                        'sex' => array(
     *                                            'name' => 'gender',
     *                                            'definition' => array(
     *                                                'type' => 'text',
     *                                                'length' => 1,
     *                                                'default' => 'M',
     *                                            ),
     *                                        )
     *                                    )
     *                                )
     *
     * @param boolean $check     indicates whether the function should just check if the DBMS driver
     *                             can perform the requested table alterations if the value is true or
     *                             actually perform them otherwise.
     * @return void
     */
    public function alterTable($name, array $changes, $check = false)
    {
        foreach ($changes as $changeName => $change) {
            switch ($changeName) {
                case 'add':
                    break;
                case 'remove':
                    break;
                case 'name':
                case 'rename':
                case 'change':
                default:
                    throw SchemaException::alterTableChangeNotSupported($changeName);
            }
        }

        $query = '';
        if ( ! empty($changes['add']) && is_array($changes['add'])) {
            foreach ($changes['add'] as $fieldName => $field) {
                if ($query) {
                    $query .= ', ';
                }
                $query .= 'ADD ' . $this->getDeclaration($fieldName, $field);
            }
        }

        if ( ! empty($changes['remove']) && is_array($changes['remove'])) {
            foreach ($changes['remove'] as $fieldName => $field) {
                if ($query) {
                    $query .= ', ';
                }
                $query .= 'DROP COLUMN ' . $fieldName;
            }
        }

        if ( ! $query) {
            return false;
        }

        return $this->_conn->exec('ALTER TABLE ' . $name . ' ' . $query);
    }

    /**
     * {@inheritdoc}
     */
    public function createSequence($seqName, $start = 1, $allocationSize = 1)
    {
        $seqcolName = 'seq_col';
        $query = 'CREATE TABLE ' . $seqName . ' (' . $seqcolName .
                 ' INT PRIMARY KEY CLUSTERED IDENTITY(' . $start . ', 1) NOT NULL)';

        $res = $this->_conn->exec($query);

        if ($start == 1) {
            return true;
        }

        try {
            $query = 'SET IDENTITY_INSERT ' . $sequenceName . ' ON ' .
                     'INSERT INTO ' . $sequenceName . ' (' . $seqcolName . ') VALUES ( ' . $start . ')';
            $res = $this->_conn->exec($query);
        } catch (Exception $e) {
            $result = $this->_conn->exec('DROP TABLE ' . $sequenceName);
        }
        return true;
    }
    /**
     * lists all database sequences
     *
     * @param string|null $database
     * @return array
     */
    public function listSequences($database = null)
    {
        $query = "SELECT name FROM sysobjects WHERE xtype = 'U'";
        $tableNames = $this->_conn->fetchAll($query);

        return array_map(array($this->_conn->formatter, 'fixSequenceName'), $tableNames);
    }
   
    /**
     * lists table views
     *
     * @param string $table     database table name
     * @return array
     */
    public function listTableViews($table)
    {
        $keyName = 'INDEX_NAME';
        $pkName = 'PK_NAME';
        if ($this->_conn->getAttribute(Doctrine::ATTR_PORTABILITY) & Doctrine::PORTABILITY_FIX_CASE) {
            if ($this->_conn->getAttribute(Doctrine::ATTR_FIELD_CASE) == CASE_LOWER) {
                $keyName = strtolower($keyName);
                $pkName  = strtolower($pkName);
            } else {
                $keyName = strtoupper($keyName);
                $pkName  = strtoupper($pkName);
            }
        }
        $table = $this->_conn->quote($table, 'text');
        $query = 'EXEC sp_statistics @table_name = ' . $table;
        $indexes = $this->_conn->fetchColumn($query, $keyName);

        $query = 'EXEC sp_pkeys @table_name = ' . $table;
        $pkAll = $this->_conn->fetchColumn($query, $pkName);

        $result = array();

        foreach ($indexes as $index) {
            if ( ! in_array($index, $pkAll) && $index != null) {
                $result[] = $this->_conn->formatter->fixIndexName($index);
            }
        }

        return $result;
    }
}
