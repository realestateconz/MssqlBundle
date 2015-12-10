<?php
/*
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

namespace Realestate\MssqlBundle\Driver\PDODblib;

use Doctrine\DBAL\Driver\Connection as DriverConnection;

/**
 * MsSql/Dblib Connection implementation.
 *
 * @since 2.0
 */
class Connection extends \Doctrine\DBAL\Driver\PDOConnection implements DriverConnection
{
    /**
     * {@inheritdoc}
     */
    private function setParams()
    {
        $this->exec("SET ANSI_WARNINGS ON");
        $this->exec("SET ANSI_PADDING ON");
        $this->exec("SET ANSI_NULLS ON");
        $this->exec("SET QUOTED_IDENTIFIER ON");
        $this->exec("SET CONCAT_NULL_YIELDS_NULL ON");
    }
    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->setParams();
        $this->exec('ROLLBACK TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->setParams();
        $this->exec('COMMIT TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->setParams();
        $this->exec('BEGIN TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        $stmt = $this->query('SELECT SCOPE_IDENTITY()');
        $id = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $id;
    }
}
