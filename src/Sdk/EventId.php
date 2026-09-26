<?php // Dihasilkan tools/rebrand.php dari hulu sdk-php — jangan diubah manual.

declare(strict_types=1);

namespace Jasnita\Monitor\Sdk;

use Jasnita\Monitor\Sdk\Util\JasnitaUid;

/**
 * This class represents an event ID.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class EventId implements \Stringable
{
    /**
     * @var string The ID
     */
    private $value;

    /**
     * Class constructor.
     *
     * @param string $value The ID
     */
    public function __construct(string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $value)) {
            throw new \InvalidArgumentException('The $value argument must be a 32 characters long hexadecimal string.');
        }

        $this->value = $value;
    }

    /**
     * Generates a new event ID.
     *
     * @copyright Matt Farina MIT License https://github.com/lootils/uuid/blob/master/LICENSE
     */
    public static function generate(): self
    {
        return new self(JasnitaUid::generate());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
