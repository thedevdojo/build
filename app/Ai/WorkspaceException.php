<?php

namespace App\Ai;

use RuntimeException;

/**
 * A workspace rule was violated (path escape, protected file, missing
 * file, …). The message is written for the model: it is returned as an
 * error tool result so the agent can correct itself and try again.
 */
class WorkspaceException extends RuntimeException {}
