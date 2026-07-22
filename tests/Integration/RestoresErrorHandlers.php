<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

/**
 * Booting a Symfony kernel installs its own exception handler
 * (Symfony\Component\ErrorHandler\ErrorHandler::handleException) and does not
 * restore it on shutdown. PHPUnit 11 compares the global handler stack at the
 * end of each test to its state at the start and marks the test "risky"
 * ("Test code or tested code removed error handlers other than its own") when
 * a handler was left behind.
 *
 * This trait records which handlers were active before the kernel boots and,
 * after shutdown, pops any handlers pushed on top so the stack is left exactly
 * as the test found it.
 */
trait RestoresErrorHandlers
{
    private mixed $baselineErrorHandler = null;
    private mixed $baselineExceptionHandler = null;

    protected function snapshotErrorHandlers(): void
    {
        $this->baselineErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        $this->baselineExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
    }

    protected function restoreErrorHandlers(): void
    {
        // Pop error handlers pushed since the snapshot.
        $guard = 0;
        while ($guard++ < 100) {
            $current = set_error_handler(static fn (): bool => false);
            restore_error_handler();

            if ($current === $this->baselineErrorHandler) {
                break;
            }

            restore_error_handler();
        }

        // Pop exception handlers pushed since the snapshot.
        $guard = 0;
        while ($guard++ < 100) {
            $current = set_exception_handler(null);
            restore_exception_handler();

            if ($current === $this->baselineExceptionHandler) {
                break;
            }

            restore_exception_handler();
        }
    }
}
