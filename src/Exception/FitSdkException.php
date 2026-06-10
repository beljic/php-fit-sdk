<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Exception;

/**
 * Marker interface implemented by every exception this library throws,
 * so consumers can catch them all with a single catch block.
 */
interface FitSdkException extends \Throwable
{
}
