<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 403: the token does not have the `api_requests` ability, or a policy
 * refused the action. Each bearer endpoint can send it.
 */
final class AuthorizationException extends ApiException {}
