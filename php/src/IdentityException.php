<?php

declare(strict_types=1);

namespace SimplyBoost\Identity;

/**
 * Input SimplyBoost would never accept: no secret, no email, a value that is
 * not an email address, a negative timestamp, a missing chatbot id.
 */
final class IdentityException extends \InvalidArgumentException
{
}
