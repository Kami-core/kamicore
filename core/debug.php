<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

$debugStart = microtime(true);

$debugSteps = [];

$debugLast  = $debugStart;

$phpErrors = [];

    $levels = [
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated'
    ];

    // Timing collection
    function debug_step(string $name) {
        if (!DEBUG_MODE) return;

        global $debugSteps, $debugStart, $debugLast;

        $now = microtime(true);

        $step = [
            'step_name'      => $name,
            'step_duration'  => round(($now - $debugLast) * 1000, 3),
            'total_duration' => round(($now - $debugStart) * 1000, 3),
        ];

        $debugSteps[] = $step;
        $debugLast = $now;
    }

    // Shutdown output
    register_shutdown_function(function () use (&$phpErrors, $levels, &$debugSteps, &$debugStart) {
        if (!DEBUG_MODE) {
            return;
        }

        // Capture fatal errors reported at shutdown.
        $lastError = error_get_last();
        if ($lastError !== null) {
            $type = $levels[$lastError['type']] ?? "Fatal";
            $phpErrors[] = sprintf(
                "[%s] %s in %s on line %d",
                $type,
                $lastError['message'],
                $lastError['file'],
                $lastError['line']
            );
        }

        if (empty($phpErrors) && empty($debugSteps)) {
            return;
        }

        if (defined('KAMI_ENDPOINT') && KAMI_ENDPOINT) {
            if (!empty($phpErrors)) {
                error_log('[Kami endpoint] ' . implode("\n", $phpErrors));
            }
            return;
        }

        echo "<div style='background:#f9f9f9;border:2px solid #666;padding:10px;margin:10px 0;font-family:monospace;font-size:13px;'>";
        echo "<b>DEBUG INFO</b><br>";

        if (!empty($phpErrors)) {
            echo "<div style='color:#900;'><b>Errors:</b><br><pre>";
            echo htmlspecialchars(implode("\n", $phpErrors));
            echo "</pre></div>";
        }

        if (!empty($debugSteps)) {
            echo "<div style='color:#006;'><b>Timing:</b><br><pre>";
            foreach ($debugSteps as $s) {
                echo sprintf(
                    "%-20s | step: %8.3f ms | total: %8.3f ms\n",
                    $s['step_name'],
                    $s['step_duration'],
                    $s['total_duration']
                );
            }
            echo "</pre></div>";
        }

        echo "</div>";
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$phpErrors, $levels) {
        if (error_reporting() === 0) {
            return false;
        }
        $type = $levels[$errno] ?? "Unknown";
        $phpErrors[] = sprintf("[%s] %s in %s on line %d", $type, $errstr, $errfile, $errline);
        return true;
    });
