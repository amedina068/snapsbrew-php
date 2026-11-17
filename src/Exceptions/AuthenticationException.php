<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 401: the token is missing or not valid.
 */
final class AuthenticationException extends ApiException {}
