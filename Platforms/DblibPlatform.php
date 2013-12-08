<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Realestate\MssqlBundle\Platforms;

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Schema\TableDiff;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;

/**
 * The DblibPlatform provides the behavior, features and SQL dialect of the
 * MsSQL database platform.
 */
class DblibPlatform extends SQLServer2008Platform
{

    /**
     * Whether the platform supports transactions.
     *
     * @return boolean
     */
    public function supportsTransactions()
    {
        return false;
    }

    /**
     * Whether the platform supports savepoints.
     *
     * @return boolean
     */
    public function supportsSavepoints()
    {
        return false;
    }
    

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $query
     * @param mixed $limit
     * @param mixed $offset
     * @link http://lists.bestpractical.com/pipermail/rt-devel/2005-June/007339.html
     * @return string
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit > 0) {
            $count = intval($limit);
            $offset = intval($offset);

            if ($offset < 0) {
                throw new DBALException("LIMIT argument offset=$offset is not valid");
            }

            if ($offset == 0) {
                // SELECT TOP DISTINCT does not work with mssql
                if(preg_match('#^SELECT\s+DISTINCT#i', $query) > 0) {
                    $query = preg_replace('/^SELECT\s+DISTINCT\s/i', 'SELECT DISTINCT TOP ' . $count . ' ', $query);
                } else {
                    $query = preg_replace('/^SELECT\s/i', 'SELECT TOP ' . $count . ' ', $query);
                }

                
            } else {
                $orderby = stristr($query, 'ORDER BY');

                if (!$orderby) {
                    $over = 'ORDER BY (SELECT 0)';
                } else {
                    $over = preg_replace('/\"[^,]*\".\"([^,]*)\"/i', '"inner_tbl"."$1"', $orderby);
                }

                // Remove ORDER BY clause from $query
                $query = preg_replace('/\s+ORDER BY(.*)/', '', $query);

                // Add ORDER BY clause as an argument for ROW_NUMBER()
                //$query = "SELECT ROW_NUMBER() OVER ($over) AS \"doctrine_rownum\", * FROM ($query) AS inner_tbl";
                $query = preg_replace('/^SELECT\s/', '', $query);

                $start = $offset + 1;
                $end = $offset + $count;

                //$query = "SELECT * FROM (SELECT ROW_NUMBER() OVER ($over) AS \"doctrine_rownum\", $query) AS doctrine_tbl WHERE doctrine_rownum BETWEEN $start AND $end";
                
                // distinct x must be first in the select list - didn't work with above
                list($select_list, $from_part) = explode('FROM', $query);
                $query = "SELECT * FROM (SELECT $select_list, ROW_NUMBER() OVER ($over) AS \"doctrine_rownum\" FROM $from_part) AS doctrine_tbl WHERE doctrine_rownum BETWEEN $start AND $end";
                
            }
        }
        
        return $query;
    }


    /**
     * Get the platform name for this instance
     *
     * @return string
     */
    public function getName()
    {
        return 'mssql';
    }


    /**
    /**
     * @override
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();

        // add uniqueidentifier
        $this->doctrineTypeMapping['uniqueidentifier'] = 'uniqueidentifier';
        // use the geography type
        $this->doctrineTypeMapping['geography'] = 'geography';
        // define this column type as a string so it works properly for now
        $this->doctrineTypeMapping['hierarchyid'] = 'string';
    }

    /**
     * @override
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s.u';
    }
}
