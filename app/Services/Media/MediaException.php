<?php

namespace App\Services\Media;

use RuntimeException;

/** A user-facing media problem (bad file, too large, unknown preset). */
class MediaException extends RuntimeException {}
