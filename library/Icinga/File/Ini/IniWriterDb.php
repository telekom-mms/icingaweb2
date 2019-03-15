<?php

namespace Icinga\File\Ini;

use Exception;
use Icinga\Application\Config;
use Icinga\Data\Db\DbConnection;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotReadableError;
use Zend_Config_Exception;

class IniWriterDb extends IniWriter
{
    /**
     * Write configuration to file and set file mode in case it does not exist yet
     *
     * @param string $filename
     * @param bool $exclusiveLock
     *
     * @throws Zend_Config_Exception
     * @throws ConfigurationError
     * @throws NotReadableError
     */
    public function write($filename = null, $exclusiveLock = false)
    {
        $filePath = isset($filename) ? $filename : $this->filename;

        $config = Config::app()->getSection('global');
        $ressource = $config->get('settings_resource');
        if ($ressource === null) {
            throw new ConfigurationError('No settings_backend configured for backends');
        }

        $db_connection = new DbConnection(ResourceFactory::getResourceConfig($ressource));
        if ($this->exists($db_connection, $filePath)) {
            $this->update($db_connection, $filePath);
        } else {
            $this->insert($db_connection, $filePath);
        }
    }

    /**
     * @param DbConnection $db_connection
     * @param $filename
     * @throws NotReadableError
     */
    protected function insert($db_connection, $filename)
    {
        try {
            $db = $db_connection->getDbAdapter();
            $data = array(IniParserDb::COLUMN_DATA => $this->render(), IniParserDb::COLUMN_FILENAME => $filename);
            $db->insert(IniParserDb::TABLE, $data);
        } catch (Exception $e) {
            throw new NotReadableError(
                'Cannot insert settings %s from database',
                $filename,
                $e
            );
        }
    }

    /**
     * @param DbConnection $db_connection
     * @param $filename
     * @throws NotReadableError
     */
    protected function update($db_connection, $filename)
    {
        try {
            $db = $db_connection->getDbAdapter();
            $data = array(IniParserDb::COLUMN_DATA => $this->render());
            $where = array(IniParserDb::COLUMN_FILENAME . ' = ?' => $filename);
            $db->update(IniParserDb::TABLE, $data, $where);
        } catch (Exception $e) {
            throw new NotReadableError(
                'Cannot update settings %s in database',
                $filename,
                $e
            );
        }
    }

    /**
     * @param DbConnection $db_connection
     * @param $filename
     * @return bool
     * @throws NotReadableError
     */
    protected function exists($db_connection, $filename)
    {
        try {
            $db = $db_connection->getDbAdapter();
            $result = $db->select()
                ->from(IniParserDb::TABLE, array(IniParserDb::COLUMN_DATA))
                ->where(IniParserDb::COLUMN_FILENAME . ' = ?', $filename)
                ->query()
                ->fetchAll();
        } catch (Exception $e) {
            throw new NotReadableError(
                'Cannot fetch settings %s from database',
                $filename,
                $e
            );
        }

        if (count($result) < 1) {
            return false;
        }
        return true;
    }
}
