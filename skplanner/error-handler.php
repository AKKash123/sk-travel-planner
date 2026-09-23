<?php
// ============================================
// SK Travel Planner — Custom Error Handler
// Logs all PHP errors to logs/error.log
// Include this in config.php
// ============================================

// Ensure logs directory exists
 $logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

 $logFile = $logDir . '/error.log';

/**
 * Custom error handler — converts errors to exceptions
 */
function skErrorHandler($errno, $errstr, $errfile, $errline) {
    // Respect error_reporting level
    if (!(error_reporting() & $errno)) return false;

    $typeMap = [
        E_ERROR             => 'ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'PARSE',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CORE_ERROR',
        E_CORE_WARNING      => 'CORE_WARNING',
        E_COMPILE_ERROR     => 'COMPILE_ERROR',
        E_COMPILE_WARNING   => 'COMPILE_WARNING',
        E_USER_ERROR        => 'USER_ERROR',
        E_USER_WARNING      => 'USER_WARNING',
        E_USER_NOTICE       => 'USER_NOTICE',
        E_STRICT            => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_DEPRECATED   => 'USER_DEPRECATED',
    ];

    $type = $typeMap[$errno] ?? "UNKNOWN($errno)";

    // Build log entry
    $entry = sprintf(
        "[%s] %s | %s: %s in %s on line %d\n",
        date('Y-m-d H:i:s'),
        $type,
        $errno,
        $errstr,
        $errfile,
        $errline
    );

    // Write to log file
    @file_put_contents($GLOBALS['skLogFile'] ?? __DIR__ . '/logs/error.log', $entry, FILE_APPEND);

    // Show user-friendly message for serious errors
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
        http_response_code(500);
        if (!headers_sent()) {
            include __DIR__ . '/error-500.php';
            exit;
        }
    }

    return true; // Don't execute PHP internal error handler
}

/**
 * Exception handler
 */
function skExceptionHandler($exception) {
    $entry = sprintf(
        "[%s] EXCEPTION | %s: %s in %s on line %d\nStack: %s\n",
        date('Y-m-d H:i:s'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    @file_put_contents($GLOBALS['skLogFile'] ?? __DIR__ . '/logs/error.log', $entry, FILE_APPEND);

    http_response_code(500);
    if (!headers_sent()) {
        include __DIR__ . '/error-500.php';
        exit;
    }
}

// Register handlers
set_error_handler('skErrorHandler');
set_exception_handler('skExceptionHandler');

// Also send PHP errors to our log file
ini_set('log_errors', 1);
ini_set('error_log', $logFile);
?>