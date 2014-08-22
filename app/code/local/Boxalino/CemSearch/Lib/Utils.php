<?php

/** @addtogroup frontend
 *
 * @{
 */

/**
 * @internal
 *
 * Boxalino CEM Frontend in PHP
 *
 * (C) 2009-2012 - Boxalino AG
 */


/**
 * Utility functions.
 *
 * @author nitro@boxalino.com
 */
class Utils
{
    /** Start time */
    private static $startTime = 0;

    /** Buffering base level */
    private static $obBaseLevel = 0;

    /** Debug flag */
    private static $debug = FALSE;

    /** Error log file */
    private static $errorFile = NULL;

    /** Error reporting level */
    private static $errorReporting = 0;

    /** Enable verbose error reporting */
    private static $verboseErrorReporting = FALSE;

    /** Error logs */
    private static $logs = array();

    /** Error logs counter */
    private static $logsCounter = 0;

    /** Maximum error logs */
    private static $logsThreshold = -1;

    /** Flag to set if failure is displayed */
    private static $failureDisplayed = FALSE;

    /** Enable statistics */
    private static $profilerEnabled = TRUE;

    /** Profiler depth */
    private static $profilerDepth = 0;

    /** Statistics markers */
    private static $profilerMarks = array();
    /**
     * List of valid organic parameters
     */
    private static $organicReferrers = array(
        "q" => array(
            'alltheweb.com', 'altavista.com', 'aol.com', 'ask.com', 'bing.com', 'google', 'kvasir.no', 'msn.com', 'mynet.com', 'ozu.es', 'search.com'
        ),
        "query" => array(
            'aol.com', 'lycos.com', 'mamma.com', 'netscape.com', 'terra.com'
        ),
        "encquery" => array(
            'aol.com'
        ),
        "search_word" => array(
            'eniro.se'
        ),
        "terms" => array(
            'about.com'
        ),
        "p" => array(
            'yahoo'
        ),
        "qs" => array(
            'alice.com', 'virgilio.it'
        ),
        "rdata/" => array(
            'voila.fr'
        ),
        "wd" => array(
            'baidu.com'
        ),
        "words" => array(
            'rambler.ru'
        )
    );

    /**
     * Constructor.
     *
     */
    private function __construct()
    {
    }

    /**
     * Debug facility, prints message if debug is enabled
     *
     * @param $message string or variable to debug
     */
    public static function debug($message)
    {
        if (self::$debug) {
            echo '<pre>';
            if (is_string($message)) {
                echo $message;
            } else {
                var_dump($message);
            }
            echo '</pre>', PHP_EOL;
        }
    }

    /**
     * Setup error handler
     *
     * @param $threshold maximum logs (0 to disable)
     */
    public static function setupErrorHandler($threshold = 0)
    {
        if ($threshold > self::$logsThreshold) {
            self::removeErrorHandler();

            self::$logsThreshold = $threshold;
            self::$verboseErrorReporting = $threshold > 0;
            if (self::$logsThreshold < 0) {
                return;
            }

            set_error_handler(array('Utils', '__trackError'), error_reporting());
        }
    }

    /**
     * Remove error handler
     *
     */
    public static function removeErrorHandler()
    {
        if (self::$logsThreshold >= 0) {
            self::$logsThreshold = -1;

            restore_error_handler();
        }
    }

    /**
     * Get current log file
     *
     * @return log file (or NULL if none)
     */
    public static function getErrorFile()
    {
        return self::$errorFile;
    }

    /**
     * Set current log file
     *
     * @param $file log file (or NULL if none)
     */
    public static function setErrorFile($file)
    {
        self::$errorFile = $file;
    }

    /**
     * Get active error reporting levels
     *
     * @return error reporting levels
     */
    public static function getErrorLevel()
    {
        return self::$errorReporting;
    }

    /**
     * Set active error reporting levels
     *
     * @param $errorReporting error reporting levels
     */
    public static function setErrorLevel($errorReporting)
    {
        self::$errorReporting = $errorReporting;
    }

    /**
     * Log an exception
     *
     * @param $e exception
     * @param $errno error type
     */
    public static function logException($e, $errno = 0)
    {
        $message = array(get_class($e) . ': ' . $e->getMessage());
        while ($e->getPrevious() != NULL) {
            $e = $e->getPrevious();
            $message[] = get_class($e) . ': ' . $e->getMessage();
        }
        Utils::logError(implode(', caused by: ', $message) . ' in ' . $e->getFile() . ':' . $e->getLine(), $errno);
    }

    /**
     * Log an error
     *
     * @param $message error message or variable
     * @param $errno error type
     */
    public static function logError($message, $errno = 0)
    {
        if (!is_string($message)) {
            $message = var_export($message, TRUE);
        }
        if (strlen(self::$errorFile) > 0) {
            @file_put_contents(self::$errorFile, gmstrftime('%F:%T') . ' [' . self::getErrorType($errno) . '] ' . str_replace("\n", "\\n", $message) . "\n", FILE_APPEND | LOCK_EX);
        } else {
            @error_log('[' . self::getErrorType($errno) . '] ' . $message . ', referrer: ' . $_SERVER['HTTP_REFERER']);
        }
    }

    /**
     * Get error type
     *
     * @param $errno error type
     * @return error "human" type
     */
    public static function getErrorType($errno)
    {
        switch ($errno) {
            case E_PARSE:
            case E_ERROR:
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return 'ERROR';

            case E_WARNING:
            case E_COMPILE_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';

            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';

            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
        }
        return 'INFO';
    }

    /**
     * Flush current errors and content
     *
     */
    public static function resetErrors()
    {
        self::$logs = array();
        self::$logsCounter = 0;
        self::__discardBuffer();
    }

    /**
     * Flush output buffering
     *
     * @param $complete TRUE to disable completely output buffering
     * @return TRUE on success, FALSE otherwise
     */
    private static function __discardBuffer($complete = FALSE)
    {
        $level = $complete ? 0 : Utils::$obBaseLevel;
        while (@ob_get_level() > $level) {
            if (!@ob_end_clean()) {
                return FALSE;
            }
        }
        if ($complete) {
            ob_implicit_flush(TRUE);
        }
        return TRUE;
    }

    /**
     * Track profiler step
     *
     * @param $depth lookup depth (1..n)
     * @param $forced force tracking
     */
    public static function profileEvent($depth = 1, $forced = FALSE)
    {
        self::profileEventLabel('-', FALSE, $depth + 1, $forced);
    }

    /**
     * Track profiler step
     *
     * @param $label profiler label
     * @param $block profiler block marker
     * @param $depth lookup depth (1..n)
     * @param $forced force tracking
     */
    public static function profileEventLabel($label, $block, $depth = 1, $forced = FALSE)
    {
        if ($forced || self::$profilerEnabled) {
            if (!is_numeric($depth)) {
                $depth = 1;
            }

            $trace = debug_backtrace(FALSE);
            if (sizeof($trace) > $depth) {
                $class = isset($trace[$depth]['class']) ? $trace[$depth]['class'] : '';
                $connector = isset($trace[$depth]['type']) ? $trace[$depth]['type'] : '';
                $method = $trace[$depth]['function'];
            } else {
                $class = NULL;
                $connector = NULL;
                $method = NULL;
            }
            self::$profilerMarks[] = array(
                'depth' => self::$profilerDepth,
                'block' => $block,
                'time' => microtime(TRUE) - self::$startTime,
                'file' => substr($trace[$depth - 1]['file'], strlen(__BOXALINO_ROOT_PATH) + 1),
                'line' => $trace[$depth - 1]['line'],
                'class' => $class,
                'connector' => $connector,
                'method' => $method,
                'label' => $label
            );
        }
    }

    /**
     * Track profiler step (begin)
     *
     * @param $label profiler label
     * @param $depth lookup depth (1..n)
     * @param $forced force tracking
     */
    public static function profileEventBegin($label, $depth = 1, $forced = FALSE)
    {
        self::$profilerDepth++;
        self::profileEventLabel($label, TRUE, $depth + 1, $forced);
    }

    /**
     * Track profiler step (end)
     *
     * @param $label profiler label
     * @param $depth lookup depth (1..n)
     * @param $forced force tracking
     */
    public static function profileEventEnd($label, $depth = 1, $forced = FALSE)
    {
        self::profileEventLabel($label, FALSE, $depth + 1, $forced);
        self::$profilerDepth--;
    }

    /**
     * Print profiler statistics
     *
     */
    public static function printProfilerInfos()
    {
        if (self::$profilerEnabled) {
            echo("\n\n");

            echo('<style type="text/css">');
            echo('ul.profiler { padding: 5px; margin: 10px 0px 0px 0px; border-top: 1px solid gray; background-color: white; list-style: none; }');
            echo('ul.profiler li, ul.profiler li * { overflow: hidden; font-size: 10px; color: black; }');
            echo('ul.profiler li .time  { float: left; width: 50px; padding: 2px 0px;  text-align: right; font-weight: bold; }');
            echo('ul.profiler li .delta { float: left; width: 50px; padding: 2px 0px; text-align: right; color: gray; }');
            echo('ul.profiler li .name  { float: left; width: 300px; padding: 2px 10px; text-align: left; color: gray; }');
            echo('ul.profiler li .label { float: left; padding: 2px 0px; border-left: 1px solid gray; color: gray; }');
            echo('ul.apc-cache { padding: 5px; margin: 0px; border-top: 1px solid gray; background-color: white; list-style: none; }');
            echo('ul.apc-cache li, ul.apc-cache li * { overflow: hidden; font-size: 10px; color: black; }');
            echo('ul.apc-cache li .hits { float: left; width: 50px; padding: 2px 0px;  text-align: right; font-weight: bold; }');
            echo('ul.apc-cache li .size { float: left; width: 50px; padding: 2px 0px;  text-align: right; color: gray; }');
            echo('ul.apc-cache li .key  { float: left; padding: 2px 10px;  text-align: right; color: gray;  }');
            echo('</style>');

            echo('<ul class="profiler">');
            $depthTimes = array(0);
            foreach (self::$profilerMarks as $mark) {
                if ($mark['block'] || !isset($depthTimes[$mark['depth']])) {
                    $depthTimes[$mark['depth']] = $mark['time'];
                }
                $lastTime = $depthTimes[$mark['depth']];

                echo('<li>');
                if ($mark['class']) {
                    $location = sprintf("%s%s%s (%d)", $mark['class'], $mark['connector'], $mark['method'], $mark['line']);
                } else {
                    $location = sprintf("%s (%d)", $mark['file'], $mark['line']);
                }
                printf(
                    '<div class="time">%.01f</div><div class="delta">%s</div><div class="name">%s</div><div class="label" style="margin-left: %dpx; padding-left: 2px;">%s</div>',
                    $mark['time'] * 1000.0,
                    $mark['block'] ? '-' : sprintf('+%.01f', ($mark['time'] - $lastTime) * 1000.0),
                    $location,
                    ($mark['depth'] - 1) * 15,
                    $mark['label']
                );
                echo('</li>');
            }
            echo('</ul>');

            if (function_exists('apc_cache_info')) {
                $cache = apc_cache_info('user');

                $items = array();
                foreach ($cache['cache_list'] as $item) {
                    $items[$item['info']] = array('key' => $item['info'], 'hits' => $item['num_hits'], 'size' => $item['mem_size']);
                }
                ksort($items);

                echo('<ul class="apc-cache">');
                foreach ($items as $item) {
                    echo('<li>');
                    printf('<div class="hits">%d x</div>', $item['hits']);
                    printf('<div class="size">%.01f [kb]</div>', $item['size'] / 1024);
                    printf('<div class="key">%s</div>', $item['key']);
                    echo('</li>');
                }
                echo('</ul>');
            }
        }
    }

    /**
     * Called to print account list
     *
     */
    public static function printAccountList()
    {
        self::enableProfiler(FALSE);

        if (!headers_sent()) {
            self::sendContentHeader('text/plain; charset=UTF-8');
        }
        $f = fopen('php://output', 'w');
        foreach (Account::iterator() as $account) {
            if ($account->id != 'admin') {
                fputcsv(
                    $f,
                    array(
                        $account->id,
                        $account->productionVersion,
                        $account->developmentVersion
                    )
                );
            }
        }
        return TRUE;
    }

    /**
     * Enable profiler
     *
     * @param $enabled enabled state
     */
    public static function enableProfiler($enabled)
    {
        self::$profilerEnabled = $enabled;
    }

    /**
     * Send http mime/size/cache headers to browser
     *
     * @param $mimeType mime-type (NULL if none)
     * @param $allowCache allow browser to cache (in seconds, 0 to disable)
     * @param $lastModification last modification time
     * @param $length content length (NULL if none)
     */
    public static function sendContentHeader($mimeType = NULL, $allowCache = 0, $lastModification = 0, $length = NULL)
    {
        if (!self::__discardBuffer(TRUE)) {
            throw new Exception('Cannot flush buffer');
        }
        if (headers_sent()) {
            throw new Exception('Headers already sent');
        }

        if ($mimeType !== NULL) {
            header('Content-Type: ' . strval($mimeType));
        }
        if ($length !== NULL) {
            header('Content-Length: ' . intval($length));
        }
        header('X-Robots-Tag: noindex, nofollow');
        if ($allowCache > 0) {
            header('Pragma: ');
            $since = self::httpIfModifiedSince();
            if ($since !== FALSE && $lastModification === $since) {
                header("HTTP/1.0 304 Not Modified");
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModification) . ' GMT');
                header('Cache-Control: ');
                return FALSE;
            }
            header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $allowCache) . ' GMT');
            if ($lastModification > 0) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModification) . ' GMT');
            } else {
                header('Last-Modified: ' . gmdate("D, d M Y H:i:s", time()) . ' GMT');
            }
            header('Cache-Control: public, max-age=' . $allowCache);
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0');
            header('Pragma: no-cache');
        }
    }

    /**
     * Get if-modified-since header
     *
     * @return timestamp or default value if none
     */
    public static function httpIfModifiedSince($defaultValue = FALSE)
    {
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
            if (strpos($since, ';') > 0) {
                $since = substr($since, 0, strpos($since, ';'));
            }
            return strtotime($since);
        }
        return $defaultValue;
    }

    /**
     * Get peer remote address
     *
     * @return remote address
     */
    public static function getRemoteAddress()
    {
        if (Utils::requestExists('clientAddress')) {
            return Utils::requestString('clientAddress');
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Check if request parameter exists
     *
     * @param $key parameter key
     * @return TRUE if exists FALSE otherwise
     */
    public static function requestExists($key)
    {
        return isset($_REQUEST[$key]);
    }

    /**
     * Get request parameter as string
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function requestString($key, $default = "")
    {
        if (isset($_REQUEST[$key])) {
            return self::filterRawString($_REQUEST[$key]);
        }
        return strval($default);
    }

    /**
     * Convert raw string
     *
     * @param $value raw string value
     * @return formatted string value
     */
    private static function filterRawString($value)
    {
        $value = strval($value);
        if (mb_detect_encoding($value) != 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8');
        }
        if (get_magic_quotes_gpc()) {
            return stripslashes($value);
        }
        return $value;
    }

    /**
     * Get request parameter as boolean
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function requestBoolean($key, $default = FALSE)
    {
        if (isset($_REQUEST[$key])) {
            $value = strtolower(self::requestString($key));
            return ($value == 'true' || $value == 'on' || floatval($value) > 0);
        }
        return $default;
    }

    /**
     * Get request parameter as number
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function requestNumber($key, $default = 0)
    {
        if (isset($_REQUEST[$key])) {
            $value = self::requestString($key);
            if (is_numeric($value)) {
                return floatval($value);
            }
            return 0;
        }
        return floatval($default);
    }

    /**
     * Get request parameter as string array
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function requestStringArray($key, $default = array())
    {
        if (isset($_REQUEST[$key])) {
            $value = $_REQUEST[$key];
            if (is_array($value)) {
                $numeric = FALSE;
                $array = array();
                foreach ($value as $key => $item) {
                    $key = self::filterRawString($key);
                    $numeric = $numeric || is_numeric($key);
                    $array[$key] = self::filterArray($item);
                }
                if ($numeric) {
                    ksort($array);
                }
                return $array;
            }
            return array(self::filterRawString($value));
        }

        $array = array();
        foreach ($default as $value) {
            $array[] = strval($value);
        }
        return $array;
    }

    /**
     * Convert array
     *
     * @param $value raw array
     * @return formatted array
     */
    private static function filterArray($value)
    {
        if (is_array($value)) {
            $numeric = FALSE;
            $array = array();
            foreach ($value as $key => $item) {
                $key = self::filterRawString($key);
                $numeric = $numeric || is_numeric($key);
                $array[$key] = self::filterArray($item);
            }
            if ($numeric) {
                ksort($array);
            }
            return $array;
        }
        return self::filterRawString($value);
    }

    /**
     * Get context parameter as boolean
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function contextBoolean($key, $default = FALSE)
    {
        if (self::contextExists($key)) {
            $value = strtolower(self::contextString($key));
            return ($value == 'true' || $value == 'on' || floatval($value) > 0);
        }
        return $default;
    }

    /**
     * Check if context parameter exists
     *
     * @param $key parameter key
     * @return TRUE if exists FALSE otherwise
     */
    public static function contextExists($key)
    {
        return (self::requestExists($key) || isset($_COOKIE[$key]));
    }

    /**
     * Get context parameter as string
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function contextString($key, $default = "")
    {
        if (isset($_REQUEST[$key])) {
            return self::filterRawString($_REQUEST[$key]);
        } else if (isset($_COOKIE[$key])) {
            return self::filterRawString($_COOKIE[$key]);
        }
        return strval($default);
    }

    /**
     * Get context parameter as number
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function contextNumber($key, $default = 0)
    {
        if (self::contextExists($key)) {
            $value = self::contextString($key);
            if (is_numeric($value)) {
                return floatval($value);
            }
            return 0;
        }
        return floatval($default);
    }

    /**
     * Get context parameter as string array
     *
     * @param $key parameter key
     * @param $default default value
     * @return parameter value or default value if doesn't exist
     */
    public static function contextStringArray($key, $default = array())
    {
        $array = array();
        if (self::contextExists($key)) {
            if (isset($_REQUEST[$key])) {
                $value = $_REQUEST[$key];
            } else if (isset($_COOKIE[$key])) {
                $value = $_COOKIE[$key];
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $array[] = self::filterRawString($item);
                }
            } else {
                $array[] = self::filterRawString($value);
            }
        } else {
            foreach ($default as $value) {
                $array[] = strval($value);
            }
        }
        return $array;
    }

    /**
     * Check if the source string starts with value.
     *
     * @param $source source string
     * @param $value value to find
     * @return TRUE if source starts with value, FALSE otherwise
     */
    public static function startsWith($source, $value)
    {
        return (strpos($source, $value) === 0);
    }

    /**
     * Check if the source string ends with value.
     *
     * @param $source source string
     * @param $value value to find
     * @return TRUE if source ends with value, FALSE otherwise
     */
    public static function endsWith($source, $value)
    {
        $index = strrpos($source, $value);
        return ($index !== FALSE && $index == (strlen($source) - strlen($value)));
    }

    /**
     * Check if the source string ends with value and remove it.
     *
     * @param $source source string
     * @param $value value to find
     * @return trimmed source
     */
    public static function stripEnding($source, $value)
    {
        $index = strrpos($source, $value);
        $index2 = strlen($source) - strlen($value);
        if ($index !== FALSE && $index == $index2) {
            return substr($source, 0, $index2);
        }
        return $source;
    }

    /**
     * Build a log chunk
     *
     * @param $value input value
     * @return cleaned up value
     */
    public static function asLogChunk($value)
    {
        $value = strtr(
            $value,
            array(
                ' ' => '|',
                '"' => ''
            )
        );
        if (strlen($value) == 0) {
            return '-';
        }
        return $value;
    }

    /**
     * Build url with parameters
     *
     * @param $uri base uri
     * @param $parameters query parameters
     * @param $fragment url fragment
     * @return full uri
     */
    public static function buildUrl($uri, $parameters = array(), $fragment = NULL)
    {
        return CEM_HttpClient::buildUrl($uri, $parameters, $fragment);
    }

    /**
     * Build alpha-numeric identifier (a-zA-Z0-9_)
     *
     * @param $value raw value
     * @return identifier value
     */
    public static function buildIdentifier($value)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $value);
    }

    /**
     * Build ISO8601 date format (YYYY-mm-dd'T'HH:ii:ss.sss+0000)
     *
     * @param $value gmt timestamp
     * @return data string
     */
    public static function buildDateISO8601($value = NULL)
    {
        return gmdate('Y-m-d\TH:i:s.000O', $value !== NULL ? $value : time());
    }

    /**
     * Parse ISO8601 date format (YYYY-mm-dd'T'HH:ii:ss.sss+0000)
     *
     * @param $value date string
     * @return gmt timestamp
     */
    public static function parseDateISO8601($value)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2}).(\d{3})([+\-])(\d{2})(\d{2})$/', $value, $matches)) {
            $delta = intval($matches[9]) * 60 * 60 + intval($matches[10]) * 60;
            if ($matches[8] == '-') {
                $delta = -$delta;
            }
            return (gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]) + $delta);
        }
        return NULL;
    }

    /**
     * Parse SQL date format (YYYY-mm-dd HH:ii:ss)
     *
     * @param $value date string
     * @return gmt timestamp
     */
    public static function parseSQLDate($value)
    {
        $time = strptime($value, '%Y-%m-%d %H:%M:%S');
        return gmmktime($time['tm_hour'], $time['tm_min'], $time['tm_sec'], $time['tm_mon'] + 1, $time['tm_mday'], $time['tm_year'] + 1900);
    }

    /**
     * Extract organic query from referrer.
     *
     * @param $referrer http referrer url
     * @return list(recognizedOrganicQuery, organicQuery)
     */
    public static function extractOrganicQuery($referrer)
    {
        if (!preg_match('/^([a-zA-Z]+):\/\/([a-zA-Z0-9.:_-]+)(\/[^?]+)?(.+)?$/i', $referrer, $matches)) {
            return array(FALSE, FALSE);
        }
        $protocol = $matches[1];
        $domain = strtolower($matches[2]);
        $path = isset($matches[3]) ? $matches[3] : '';
        $parameters = array();
        $query = FALSE;
        if (isset($matches[4]) && strlen($matches[4]) > 1) {
            foreach (explode('&', substr($matches[4], 1)) as $entry) {
                $entry = explode('=', $entry);
                if (sizeof($entry) != 2) {
                    continue;
                }
                $parameters[urldecode($entry[0])] = urldecode($entry[1]);
            }
        }
        foreach (self::$organicReferrers as $param => $domains) {
            if (!isset($parameters[$param])) {
                continue;
            }
            $query = urldecode($parameters[$param]);
            foreach ($domains as $entry) {
                if (strpos($domain, $entry) !== FALSE) {
                    return array($query, $query);
                }
            }
        }
        return array(FALSE, $query);
    }

    /**
     * Send given file to browser
     *
     * @param $path file path with extension
     * @param $allowCache allow browser to cache (in seconds)
     * @param $mimeType mime type
     * @return TRUE on success, FALSE on failure
     */
    public static function sendFile($path, $allowCache = 60, $mimeType = NULL)
    {
        if ($mimeType === NULL) {
            $mimeType = self::findMimeType($path);
            if (strpos($mimeType, 'text/') === 0) {
                $mimeType .= '; charset=UTF-8';
            }
        }
        self::sendContentHeader($mimeType, $allowCache, filemtime($path), filesize($path));
        return (readfile($path) !== FALSE);
    }

    /**
     * Find file mime type based on file extension
     *
     * @param $path file path with extension
     * @return mime type
     */
    public static function findMimeType($path)
    {
        $length = strlen($path);
        $slash = strrpos($path, '/');
        $extension = strrpos($path, '.');
        if ($extension > $slash && ($length - $extension) <= 6) {
            switch (strtolower(substr($path, $extension))) {
                case '.css':
                    return 'text/css';
                case '.html':
                case '.tpl':
                    return 'text/html';
                case '.js':
                    return 'text/javascript';
                case '.php':
                    return 'text/php';
                case '.log':
                case '.sql':
                case '.txt':
                    return 'text/plain';
                case '.xml':
                    return 'text/xml';

                case '.gif':
                    return 'image/gif';
                case '.jpg':
                case '.jpeg':
                    return 'image/jpeg';
                case '.png':
                    return 'image/png';
            }
        }
        return 'application/octet-stream';
    }

    /**
     * Authenticate user (http-auth)
     *
     * @param $callback authentication callback (receive user and password and returns TRUE when matched)
     * @param $advertise advertise authentication is required
     * @param $realm authentication realm
     * @return TRUE if authentication succeeded, FALSE otherwise
     */
    public static function httpAuthenticate($callback, $advertise = TRUE, $realm = 'Protected Resource Access')
    {
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) &&
            call_user_func($callback, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])
        ) {
            return TRUE;
        }
        if ($advertise) {
            header('HTTP/1.0 401 Unauthorized');
            header('WWW-Authenticate: Basic realm="' . $realm . '"');
            exit(0);
        }
        return FALSE;
    }


    /**
     * Check that the given path lies within root path
     *
     * @param $rootPath root path (must exists)
     * @param $path path to check
     */
    public static function assertPathSandbox($rootPath, $path)
    {
        if (!file_exists($rootPath) || !is_dir($rootPath)) {
            throw new Exception("Invalid root path: " . $rootPath);
        }
        $realPath = realpath($path);
        while ($realPath === FALSE) {
            $path = dirname($path . 'name');
            if (strlen($path) <= 1) {
                throw new Exception("Invalid path: " . $path);
            }
            $realPath = realpath($path);
        }
        if (strpos($realPath, realpath($rootPath)) !== 0) {
            throw new Exception("Invalid path: " . $path);
        }
    }

    /**
     * Check directory tree content
     *
     * @param $path source path
     * @param $files directory content
     * @return TRUE on success, FALSE on failure
     */
    public static function checkTree($path, $files)
    {
        foreach ($files as $itemPath => $info) {
            switch ($info['type']) {
                case 'directory':
                    if (!is_dir($path . $itemPath)) {
                        return FALSE;
                    }
                    break;
                case 'file':
                    if (!is_file($path . $itemPath) ||
                        filesize($path . $itemPath) != $info['size'] ||
                        strcasecmp(sha1_file($path . $itemPath), $info['checksum']) != 0
                    ) {
                        return FALSE;
                    }
                    break;
                case 'link':
                    if (!is_link($path . $itemPath) ||
                        strcmp(readlink($path . $itemPath), $path . $info['target']) != 0
                    ) {
                        return FALSE;
                    }
                    break;
            }
        }
        return TRUE;
    }

    /**
     * Apply directory tree content (set mtime and check consistency)
     *
     * @param $path source path
     * @param $files directory content
     * @return TRUE on success, FALSE on failure
     */
    public static function applyTree($path, $files)
    {
        foreach ($files as $itemPath => $info) {
            switch ($info['type']) {
                case 'directory':
                    if (!is_dir($path . $itemPath)) {
                        return FALSE;
                    }
                    break;
                case 'file':
                    if (!is_file($path . $itemPath) ||
                        !touch($path . $itemPath, $info['mtime']) ||
                        filesize($path . $itemPath) != $info['size'] ||
                        strcasecmp(sha1_file($path . $itemPath), $info['checksum']) != 0
                    ) {
                        return FALSE;
                    }
                    break;
                case 'link':
                    if (!is_link($path . $itemPath) ||
                        strcmp(readlink($path . $itemPath), $path . $info['target']) != 0
                    ) {
                        return FALSE;
                    }
                    break;
            }
        }
        return TRUE;
    }

    /**
     * List directory tree content
     *
     * @param $path source path
     * @param $pathPrefix path prefix
     * @param $previous previous content
     * @return directory content
     */
    public static function listTree($path, $pathPrefix = FALSE, $previous = array())
    {
        $list = array();
        if (is_dir($path)) {
            $dh = opendir($path);
            if ($dh) {
                while (($item = readdir($dh)) !== FALSE) {
                    if ($item == '.' || $item == '..') {
                        continue;
                    }
                    $itemPath = $path . '/' . $item;
                    if ($pathPrefix && strpos($itemPath, $pathPrefix) === 0) {
                        $keyPath = substr($itemPath, strlen($pathPrefix));
                    } else {
                        $keyPath = $itemPath;
                    }
                    if (is_dir($itemPath)) {
                        $list[$keyPath] = array('type' => 'directory');
                        foreach (self::listTree($itemPath, $pathPrefix, $previous) as $childPath => $info) {
                            $list[$childPath] = $info;
                        }
                    } else if (is_link($itemPath)) {
                        $targetPath = readlink($itemPath);
                        if ($pathPrefix && strpos($targetPath, $pathPrefix) === 0) {
                            $targetPath = substr($targetPath, strlen($pathPrefix));
                        }
                        $list[$keyPath] = array('type' => 'link', 'target' => $targetPath);
                    } else if (is_file($itemPath)) {
                        if (isset($previous[$keyPath]) && $previous[$keyPath]['type'] == 'file' &&
                            $previous[$keyPath]['mtime'] == filemtime($itemPath) &&
                            $previous[$keyPath]['size'] == filesize($itemPath)
                        ) {
                            $checksum = $previous[$keyPath]['checksum'];
                        } else {
                            $checksum = sha1_file($itemPath);
                        }
                        $list[$keyPath] = array(
                            'type' => 'file',
                            'mtime' => filemtime($itemPath),
                            'size' => filesize($itemPath),
                            'checksum' => $checksum
                        );
                    }
                }
                closedir($dh);
            }
        }
        return $list;
    }

    /**
     * Copy directory tree
     *
     * @param $src source path
     * @param $dst destination path
     * @param $dirMode directory permissions
     * @param $fileMode file permissions
     * @param $override override flag
     * @return TRUE on success, FALSE on failure
     */
    public static function copyTree($src, $dst, $dirMode = 02777, $fileMode = 0666, $override = TRUE)
    {
        if (!is_dir($src) || !is_dir($dst)) {
            return FALSE;
        }

        $dh = opendir($src);
        if (!$dh) {
            return FALSE;
        }
        while (($item = readdir($dh)) !== FALSE) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            if (is_dir($srcPath)) {
                if (file_exists($dstPath) && !is_dir($dstPath)) {
                    return FALSE;
                }
                if (file_exists($dstPath)) {
                    if ($override) {
                        if (!self::copyTree($srcPath, $dstPath, $dirMode, $fileMode, $override)) {
                            return FALSE;
                        }
                    }
                } else {
                    if (!mkdir($dstPath) || !chmod($dstPath, $dirMode)) {
                        return FALSE;
                    }
                    if (!self::copyTree($srcPath, $dstPath, $dirMode, $fileMode, $override)) {
                        return FALSE;
                    }
                }
            } else if (is_file($srcPath)) {
                if (file_exists($dstPath) && !is_file($dstPath)) {
                    return FALSE;
                }
                if ($override || !file_exists($dstPath)) {
                    if (!copy($srcPath, $dstPath) || !chmod($dstPath, $fileMode)) {
                        return FALSE;
                    }
                }
            } else {
                return FALSE;
            }
        }
        closedir($dh);
        return TRUE;
    }

    /**
     * Delete directory tree
     *
     * @param $src source path
     * @param $self delete also source path itself
     * @return TRUE on success, FALSE on failure
     */
    public static function deleteTree($src, $self = TRUE)
    {
        if (!is_dir($src)) {
            return FALSE;
        }

        $dh = opendir($src);
        if (!$dh) {
            return FALSE;
        }
        while (($item = readdir($dh)) !== FALSE) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            if (is_dir($srcPath)) {
                if (!self::deleteTree($srcPath, TRUE)) {
                    return FALSE;
                }
            } else if (is_file($srcPath)) {
                if (!unlink($srcPath)) {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }
        closedir($dh);

        if ($self) {
            return rmdir($src);
        }
        return TRUE;
    }


    /**
     * Pack directory tree in a zip file
     *
     * @param $src source path
     * @param $dst destination zip file
     * @return TRUE on success, FALSE on failure
     */
    public static function packTree($src, $dst)
    {
        $zip = new ZipArchive();
        if (!$zip->open($dst, ZipArchive::CREATE)) {
            return FALSE;
        }
        if (!self::packTreeNode($src, $zip, "")) {
            $zip->close();
            return FALSE;
        }
        if (!$zip->close()) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Pack directory node in a zip file
     *
     * @param $src source path
     * @param $zip zip file
     * @param $dstPrefix destination path prefix
     * @return TRUE on success, FALSE on failure
     */
    private static function packTreeNode($src, $zip, $dstPrefix)
    {
        if (!is_dir($src)) {
            return FALSE;
        }

        $dh = opendir($src);
        if (!$dh) {
            return FALSE;
        }
        while (($item = readdir($dh)) !== FALSE) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            if (is_dir($srcPath)) {
                if (!$zip->addEmptyDir($dstPrefix . $item)) {
                    return FALSE;
                }
                if (!self::packTreeNode($srcPath, $zip, $dstPrefix . $item . '/')) {
                    return FALSE;
                }
            } else if (is_file($srcPath)) {
                if (!$zip->addFile($srcPath, $dstPrefix . $item)) {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }
        closedir($dh);

        return TRUE;
    }

    /**
     * Unpack directory tree from a zip file
     *
     * @param $src source zip file
     * @param $dst destination path
     * @return TRUE on success, FALSE on failure
     */
    public static function unpackTree($src, $dst)
    {
        if (!is_dir($dst)) {
            return FALSE;
        }

        $zip = new ZipArchive();
        if (!$zip->open($src)) {
            return FALSE;
        }
        if (!$zip->extractTo($dst)) {
            $zip->close();
            return FALSE;
        }
        if (!$zip->close()) {
            return FALSE;
        }
        return TRUE;
    }


    /**
     * Load a CSV file into the database
     *
     * @param $path CSV file path
     * @param $table database table to load data into
     * @param $merge replace element in table if TRUE or just append new element if FALSE
     * @return TRUE on success, FALSE on failure
     */
    public static function loadCSVIntoTable($path, $table, $merge = FALSE)
    {
        $sql = SQLConnection::create();

        $f = @fopen($path, 'r');
        if (!$f) {
            return FALSE;
        }
        $success = TRUE;
        while ($line = fgetcsv($f, 0, ';', '"', '\\')) {
            $parameters = array();
            foreach ($line as $index => $value) {
                if ($value == 'NULL') {
                    $line[$index] = NULL;
                } else {
                    $line[$index] = str_replace('\\\\', '\\', str_replace('\\"', '"', $value));
                }
                $parameters[] = '?';
            }
            if ($merge) {
                if (!$sql->update('REPLACE INTO `' . $table . '` VALUES ( ' . implode(', ', $parameters) . ' )', $line)) {
                    $success = FALSE;
                }
            } else {
                if (!$sql->update('INSERT IGNORE INTO `' . $table . '` VALUES ( ' . implode(', ', $parameters) . ' )', $line)) {
                    $success = FALSE;
                }
            }
        }
        fclose($f);
        return $success;
    }

    /**
     * Save a database table into a CSV file
     *
     * @param $table database table to save data from
     * @param $orderBy order by field name
     * @param $path CSV file path
     * @return TRUE on success, FALSE on failure
     */
    public static function saveTableIntoCSV($table, $orderBy, $path)
    {
        $sql = SQLConnection::create();

        $f = @fopen($path, 'w');
        if (!$f) {
            return FALSE;
        }
        $success = TRUE;
        if ($sql->query('SELECT * FROM `' . $table . '` ORDER BY `' . $orderBy . '` ASC')) {
            while ($row = $sql->next()) {
                $list = array();
                foreach ($row as $cell) {
                    if ($cell === NULL) {
                        $list[] = 'NULL';
                    } else {
                        $list[] = $cell;
                    }
                }
                if (!fputcsv($f, $list, ';', '"')) {
                    $success = FALSE;
                }
            }
        }
        fclose($f);
        return $success;
    }

    /**
     * Store CSV file from input stream to database and file
     *
     * @param $db opened database
     * @param $table table name
     * @param $fields table fields (field => definition)
     * @param $primaryKey table primary key (field => definition)
     * @param $path target file path or NULL
     * @return TRUE on success, FALSE on failure
     */
    public static function storeCSV($db, $table, $fields, $primaryKey = array(), $path = NULL)
    {
        if (!$db->beginTransaction()) {
            return FALSE;
        }
        $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
        $sql = array();
        foreach ($fields as $field => $definition) {
            $sql[] = '`' . $field . '` ' . $definition;
        }
        if (sizeof($primaryKey) > 0) {
            $sql2 = array();
            foreach ($primaryKey as $field => $definition) {
                $sql2[] = '`' . $field . '` ' . $definition;
            }
            $sql[] = 'PRIMARY KEY ( ' . implode(', ', $sql2) . ' )';
        }
        if ($db->exec('CREATE TABLE `' . $table . '` ( ' . implode(', ', $sql) . ' )') === FALSE) {
            $db->rollBack();
            return FALSE;
        }
        if ($db->exec('DELETE FROM `' . $table . '`') === FALSE) {
            $db->rollBack();
            return FALSE;
        }
        if (!($st = $db->prepare('INSERT INTO `' . $table . '` VALUES ( ' . implode(', ', array_fill(0, sizeof($fields), '?')) . ' )'))) {
            $db->rollBack();
            return FALSE;
        }
        $in = fopen('php://input', 'r');
        if (!$in) {
            $db->rollBack();
            return FALSE;
        }
        if ($path !== NULL) {
            $out = fopen($path, 'w');
            if (!$out) {
                fclose($in);
                $db->rollBack();
                return FALSE;
            }
        } else {
            $out = FALSE;
        }
        while ($row = fgetcsv($in)) {
            if (!$st->execute($row)) {
                fclose($in);
                fclose($out);
                $db->rollBack();
                return FALSE;
            }
            if ($path !== NULL) {
                if (!fputcsv($out, $row)) {
                    fclose($in);
                    fclose($out);
                    $db->rollBack();
                    return FALSE;
                }
            }
        }
        fclose($in);
        fclose($out);
        if (!$db->commit()) {
            return FALSE;
        }
        return TRUE;
    }


    /**
     * Prepare the input string for xml
     *
     * @param $value input string
     * @return filtered string
     */
    public static function filterXmlString($value)
    {
        return strtr(
            $value,
            array(
                "\0" => '',
                "\x01" => '&#x01;',
                "\x02" => '&#x02;',
                "\x03" => '&#x03;',
                "\x04" => '&#x04;',
                "\x05" => '&#x05;',
                "\x06" => '&#x06;',
                "\x07" => '&#x07;',
                "\x08" => '&#x08;',
                "\x0b" => '&#x0b;',
                "\x0c" => '&#x0c;',
                "\x0e" => '&#x0e;',
                "\x0f" => '&#x0f;',
                "\x10" => '&#x10;',
                "\x11" => '&#x11;',
                "\x12" => '&#x12;',
                "\x13" => '&#x13;',
                "\x14" => '&#x14;',
                "\x15" => '&#x15;',
                "\x16" => '&#x16;',
                "\x17" => '&#x17;',
                "\x18" => '&#x18;',
                "\x19" => '&#x19;',
                "\x1a" => '&#x1a;',
                "\x1b" => '&#x1b;',
                "\x1c" => '&#x1c;',
                "\x1d" => '&#x1d;',
                "\x1e" => '&#x1e;',
                "\x1f" => '&#x1f;',
                "\xff" => '&#xff;',
                "\ufffe" => '',
                "\uffff" => ''
            )
        );
    }

    /**
     * Load a file from given url (overrides local file only on success).
     *
     * @param $path target path (local storage)
     * @param $url source url (remote)
     * @return TRUE on success, FALSE on failure
     */
    public static function loadFileFromUrl($path, $url)
    {
        $client = new CEM_HttpClient(FALSE, FALSE, 5000, 15000);
        if ($client->getAndSave($url) != 200) {
            Utils::logError('cannot load file: ' . $url);
            $client->removeFile();
            return FALSE;
        }

// copy to final destination
        if (!copy($client->getFile(), $path) || !chmod($path, __BOXALINO_FILE_MODE)) {
            $client->removeFile();
            return FALSE;
        }
        $client->removeFile();
        return TRUE;
    }

    /**
     * Proxy current request to remote server
     *
     * @param $url destination url
     * @param $username destination username for authentication (optional)
     * @param $password destination password for authentication (optional)
     * @return TRUE on success, FALSE on failure
     */
    public static function proxyRequest($url, $username = FALSE, $password = FALSE)
    {
        $proxy = new CEM_SimpleProxy($username, $password);
        $proxy->sendRequest($url);
        return $proxy->writeResponse();
    }

    /**
     * Called at the bottom of the file (internal)
     *
     */
    public static function __init()
    {
        self::$errorReporting = error_reporting();
        self::$startTime = microtime(TRUE);
        self::$obBaseLevel = ob_get_level();
        self::$debug = (self::requestString('debug') == 'true');
        if (!@ob_start()) {
            trigger_error('Error: cannot start buffering.', E_USER_ERROR);
        }
        set_exception_handler(array('Utils', '__trackException'));
        register_shutdown_function(array('Utils', '__trackShutdown'));
    }

    /**
     * Called by php when the script ends
     *
     */
    public static function __trackShutdown()
    {
        /*	 $error = error_get_last();
        if ($error && (self::$errorReporting & $error['type'] & (E_ERROR | E_CORE_ERROR | E_USER_ERROR)) !== 0) {
        self::logError($error['message'].' in '.$error['file'].':'.$error['line'], $error['type']);
        self::$logsCounter++;

        if (self::$logsThreshold > 0 && sizeof(self::$logs) < self::$logsThreshold) {
        self::$logs[] = array($error['type'], $error['message'], $error['file'], $error['line']);
        }
        }*/
        if (self::hasErrors()) {
            self::failure(500, 'Internal Server Error', 'Error: internal error(s) encountered.');
        }
    }

    /**
     * Check if there are any error logs
     *
     * @return TRUE if error(s) FALSE otherwise
     */
    public static function hasErrors()
    {
        return (self::$logsCounter > 0);
    }

    /**
     * Report generic failure and abort processing
     *
     * @param $code error code
     * @param $message error message
     * @param $description error description (optional)
     * @param $shift stack trace shift (optional)
     */
    public static function failure($code, $message, $description = '', $shift = 0)
    {
        if (self::$failureDisplayed) {
            return;
        }

        self::$failureDisplayed = TRUE;
        self::removeErrorHandler();
        self::__discardBuffer();

        if (!headers_sent()) {
            header("HTTP/1.0 $code $message");

            self::sendContentHeader('text/html; charset=UTF-8');
        }

        echo("<html><head><title></title></head><body><h1>$message</h1>");
        if (strlen($description) > 0) {
            if (strpos($description, '<') === 0) {
                echo($description);
            } else {
                echo("<p>" . htmlentities($description) . "</p>");
            }
        }
        if (self::$verboseErrorReporting && $code >= 500 && $code < 600) {
            if ($shift >= 0) {
                echo("<p>Stacktrace:</p>");
                self::printStackTrace($shift);
            }
            self::printErrors();
        }
        echo('</body></html>');
        flush();

        exit(1);
    }

    /**
     * Print stack trace
     *
     * @param $shift ignore top n traces
     */
    public static function printStackTrace($shift = 0)
    {
        if (!self::$failureDisplayed) {
            self::__discardBuffer();
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");

                self::sendContentHeader('text/html; charset=UTF-8');
            }

            echo("<html><head><title></title></head><body><h1>Stack trace</h1>");
        }
        $trace = debug_backtrace();
        for ($i = 0; $i <= $shift; $i++) {
            array_shift($trace);
        }
        if (sizeof($trace) > 0) {
            echo('<pre>');
            foreach ($trace as $entry) {
                if (!isset($entry['class']) || !isset($entry['file'])) {
                    continue;
                }
                echo('  ' . $entry['class'] . $entry['type'] . $entry['function'] . '(');
                foreach ($entry['args'] as $i => $arg) {
                    if ($i > 0) {
                        echo(', ');
                    }
                    if (is_bool($arg)) {
                        echo($arg ? 'TRUE' : 'FALSE');
                    } else if (is_numeric($arg)) {
                        echo($arg);
                    } else if (is_string($arg)) {
                        echo('"' . addslashes($arg) . '"');
                    } else if (is_object($arg)) {
                        echo('{' . get_class($arg) . '}');
                    } else if (is_array($arg)) {
                        echo('[array]');
                    } else {
                        echo($arg);
                    }
                }
                echo(")\n");
                echo('    called from ' . substr($entry['file'], strlen(__DIR__) - 3) . ':' . $entry['line'] . "\n");
            }
            echo('</pre>');
        }
        if (!self::$failureDisplayed) {
            echo('</body></html>');
            flush();

            exit(1);
        }
    }

    /**
     * Print error logs if any
     *
     */
    public static function printErrors()
    {
        if (self::$logsCounter > 0) {
            echo("<pre>PHP errors (" . self::$logsCounter . "): \n");
            foreach (self::$logs as $log) {
                echo('  ' . $log[1] . "\n");
                echo('    in ' . substr($log[2], strlen(__DIR__) - 3) . ':' . $log[3] . "\n");
            }
            if (sizeof(self::$logs) < self::$logsCounter) {
                echo("\n" . (self::$logsCounter - sizeof(self::$logs)) . " more...\n");
            }
            echo('</pre>');
        }
    }

    /**
     * Called by php when an exception occurs
     *
     * @param $exception exception
     */
    public static function __trackException($exception)
    {
        while ($exception->getPrevious() != NULL) {
            $exception = $exception->getPrevious();
        }

        if ((self::$errorReporting & E_ERROR) === E_ERROR) {
            self::logError(get_class($exception) . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine() . ' (' . $exception->getTraceAsString() . ')', E_USER_ERROR);
            self::$logsCounter++;

            if (self::$logsThreshold > 0 && sizeof(self::$logs) < self::$logsThreshold) {
                self::$logs[] = array(
                    E_USER_ERROR,
                    '<p>' . htmlentities(get_class($exception) . ': ' . $exception->getMessage()) . '</p><pre>' . $exception->getFile() . ':' . $exception->getLine() . '</pre><pre>' . htmlentities($exception->getTraceAsString()) . '</pre>',
                    $exception->getFile(),
                    $exception->getLine()
                );
            }
        }

        self::failure(500, 'Internal Server Error', $exception->getMessage(), -1);
    }

    /**
     * Called by php when an error occurs
     *
     * @param $errno error code
     * @param $message error message
     * @param $file error location
     * @param $line error location
     */
    public static function __trackError($errno, $message, $file, $line)
    {
        if (error_reporting() !== 0 && (self::$errorReporting & $errno) === $errno) {
            self::logError($message . ' in ' . $file . ':' . $line, $errno);
            self::$logsCounter++;

            if (self::$logsThreshold > 0 && sizeof(self::$logs) < self::$logsThreshold) {
                self::$logs[] = array($errno, $message, $file, $line);
            }
        }
        return TRUE;
    }
}

Utils::__init();

/**
 * @}
 */
