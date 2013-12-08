<?php

namespace Realestate\MssqlBundle\Schema;

use Doctrine\DBAL\Schema\SQLServerSchemaManager;

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

class DblibSchemaManager extends SQLServerSchemaManager
{


  
   

    protected function _getPortableSequenceDefinition($sequence)
    {
        return end($sequence);
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
