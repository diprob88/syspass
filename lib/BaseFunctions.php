<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * [type] [caller] data
 */
define('LOG_FORMAT', "[%s] [%s] %s");
/**
 * [timestamp] [type] [caller] data
 */
define('LOG_FORMAT_OWN', "%s [%s] [%s] %s\n");

/**
 * Basic logger to handle some debugging and exception messages.
 *
 * It will log messages into syspass.log or PHP error log file.
 *
 * In order to log debugging messages, DEBUG constant must be set to true.
 *
 * A more advanced event logging should be handled through EventDispatcher
 *
 * @param mixed $data
 * @param string $type
 */
function logger($data, $type = 'DEBUG')
{
    if (!DEBUG && $type === 'DEBUG') {
        return;
    }

    $date = date('Y-m-d H:i:s');
    $caller = getLastCaller();

    if (is_scalar($data)) {
        $line = sprintf(LOG_FORMAT_OWN, $date, $type, $caller, $data);
    } else {
        $line = sprintf(LOG_FORMAT_OWN, $date, $type, $caller, print_r($data, true));
    }

    $useOwn = (!defined('LOG_FILE')
        || !error_log($line, 3, LOG_FILE)
    );

    if ($useOwn === false) {
        if (is_scalar($data)) {
            $line = sprintf(LOG_FORMAT, $type, $caller, $data);
        } else {
            $line = sprintf(LOG_FORMAT, $type, $caller, print_r($data, true));
        }

        error_log($line);
    }
}

/**
 * Print last callers from backtrace
 */
function printLastCallers()
{
    logger(formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
}

/**
 * Print last caller from backtrace
 *
 * @return string
 */
function getLastCaller()
{
    $callers = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

    if (isset($callers[2])
        && isset($callers[2]['class'])
        && isset($callers[2]['function'])
    ) {
        return $callers[2]['class'] . '::' . $callers[2]['function'];
    }

    return 'N/A';
}

/**
 * Process an exception and log into the error log
 *
 * @param \Exception $exception
 */
function processException(\Exception $exception)
{
    logger(sprintf("%s\n%s", __($exception->getMessage()), $exception->getTraceAsString()), 'EXCEPTION');

    if (($previous = $exception->getPrevious()) !== null) {
        logger(sprintf("(P) %s\n%s", __($previous->getMessage()), $previous->getTraceAsString()), 'EXCEPTION');
    }
}

/**
 * @param $trace
 *
 * @return string
 */
function formatTrace($trace)
{
    $btLine = [];
    $i = 0;

    foreach ($trace as $caller) {
        $class = isset($caller['class']) ? $caller['class'] : '';
        $file = isset($caller['file']) ? $caller['file'] : '';
        $line = isset($caller['line']) ? $caller['line'] : 0;

        $btLine[] = sprintf('Caller %d: %s\%s (%s:%d)', $i, $class, $caller['function'], $file, $line);
        $i++;
    }

    return implode(PHP_EOL, $btLine);
}

/**
 * Alias gettext function
 *
 * @param string $message
 * @param bool $translate Si es necesario traducir
 *
 * @return string
 */
function __($message, $translate = true)
{
    return $translate === true && $message !== '' && mb_strlen($message) < 4096 ? gettext($message) : $message;
}

/**
 * Returns an untranslated string (gettext placeholder).
 * Dummy function to extract strings from source code
 *
 * @param string $message
 *
 * @return string
 */
function __u($message)
{
    return $message;
}

/**
 * Alias para obtener las locales de un dominio
 *
 * @param string $domain
 * @param string $message
 * @param bool $translate
 *
 * @return string
 */
function _t($domain, $message, $translate = true)
{
    return $translate === true && $message !== '' && mb_strlen($message) < 4096 ? dgettext($domain, $message) : $message;
}

/**
 * Capitalización de cadenas multi byte
 *
 * @param $string
 *
 * @return string
 */
function mb_ucfirst($string)
{
    return mb_strtoupper(mb_substr($string, 0, 1));
}

/**
 * Devuelve el tiempo actual en coma flotante.
 * Esta función se utiliza para calcular el tiempo de renderizado con coma flotante
 *
 * @param float $from
 *
 * @returns float con el tiempo actual
 * @return float
 */
function getElapsedTime($from)
{
    if ($from === 0) {
        return 0;
    }

    return microtime(true) - floatval($from);
}

/**
 * Inicializar módulo
 *
 * @param $module
 */
function initModule($module)
{
    $dir = dir(MODULES_PATH);

    while (false !== ($entry = $dir->read())) {
        $moduleFile = MODULES_PATH . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'module.php';

        if ($entry === $module && file_exists($moduleFile)) {
            require $moduleFile;
        }
    }

    $dir->close();
}

/**
 * @param $dir
 * @param $levels
 *
 * @return bool|string
 */
function nDirname($dir, $levels)
{
    if (version_compare(PHP_VERSION, '7.0') === -1) {
        logger(realpath(dirname($dir) . str_repeat('../', $levels)));

        return realpath(dirname($dir) . str_repeat('../', $levels));
    }

    return dirname($dir, $levels);
}

/**
 * Prints a fancy trace info using Xdebug extension
 */
function printTraceInfo()
{
    if (DEBUG && extension_loaded('xdebug')) {
        xdebug_print_function_stack();
    }
}
