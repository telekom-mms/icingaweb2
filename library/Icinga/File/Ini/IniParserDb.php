<?php

namespace Icinga\File\Ini;

use ErrorException;
use Exception;
use Icinga\Application\Config;
use Icinga\Data\Db\DbConnection;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotReadableError;

class IniParserDb extends IniParser
{
    const TABLE = 'icingaweb_settings';

    const COLUMN_FILENAME = 'filename';

    const COLUMN_DATA = 'data';

    /**
     * Read the ini file and parse it with ::parseIni()
     *
     * @param   string $file The ini file to read
     *
     * @return  Config
     * @throws  NotReadableError    When the file cannot be read
     * @throws ConfigurationError
     */
    public static function parseIniFile($file)
    {
        if (($path = realpath($file)) === false) {
            throw new NotReadableError('Couldn\'t compute the absolute path of `%s\'', $file);
        }

        $config = Config::app()->getSection('global');
        $ressource = $config->get('settings_resource');
        if ($ressource === null) {
            throw new ConfigurationError('No settings_backend configured for backends');
        }

        $db_connection = new DbConnection(ResourceFactory::getResourceConfig($ressource));
        try {
            $db = $db_connection->getDbAdapter();
            $result = $db->select()
                ->from(self::TABLE, array(self::COLUMN_DATA))
                ->where(self::COLUMN_FILENAME . ' = ?', $path)
                ->query()
                ->fetchAll();
        } catch (Exception $e) {
            throw new NotReadableError(
                'Cannot fetch settings %s from database',
                $path,
                $e
            );
        }

        $content = '';
        if (count($result) <= 1) {
            $row = array_shift($result);
            if (is_object($row)) {
                $content = $row->{self::COLUMN_DATA};
            }
        } else {
            throw new ConfigurationError("found ".count($result)." settings for $path in database");
        }

        try {
            $configArray = parse_ini_string($content, true);
        } catch (ErrorException $e) {
            throw new ConfigurationError('Couldn\'t parse the INI file `%s\'', $path, $e);
        }

        return Config::fromArray($configArray)->setConfigFile($file);
    }
}
