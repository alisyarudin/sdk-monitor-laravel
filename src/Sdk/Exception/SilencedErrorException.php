<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk\Exception;

/**
 * Error that has been captured by the {@see ErrorHandler}, but that was silenced
 * with `@` in the source code.
 */
final class SilencedErrorException extends \ErrorException
{
}
