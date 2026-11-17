<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 5xx: the instance had an error.
 *
 * The body of a 500 from `POST /v1/snaps` or `POST /v1/snaps/batches` has a
 * fixed message and the code `internal_error`. Give `requestUuid()` in a
 * support request.
 */
final class ServerException extends ApiException {}
