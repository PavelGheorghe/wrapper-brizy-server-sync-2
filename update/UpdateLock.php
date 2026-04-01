<?php

declare(strict_types=1);

class UpdateLock
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(string $lockPath)
    {
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new UpdateException('Unable to open update lock file.');
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            throw new UpdateException('Another update is already in progress.', 409);
        }

        $this->handle = $handle;
    }

    public function release()
    {
        if (is_resource($this->handle)) {
            @flock($this->handle, LOCK_UN);
            @fclose($this->handle);
            $this->handle = null;
        }
    }
}
