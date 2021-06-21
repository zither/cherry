<?php
namespace Cherry\Session;

use Cherry\Collection;

abstract class AbstractSession extends Collection implements SessionInterface
{
    /**
     * @var string
     */
    protected $name = 'PHPSESSID';

    /**
     * @var bool $started
     */
    protected $started = false;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $token;

    /**
     * @return string
     * @throws \Exception
     */
    protected function generateRandomToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * Starts the session storage.
     *
     * @return bool True if session started
     *
     * @throws \RuntimeException if session fails to start
     * @throws \Exception
     */
    abstract public function start(): bool;

    /**
     * Returns the session ID.
     *
     * @return string The session ID
     * @throws \Exception
     */
    public function getId(): string
    {
        return $this->token;
    }

    /**
     * Sets the session ID.
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->token = $id;
    }

    /**
     * Returns the session name.
     *
     * @return mixed The session name
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Sets the session name.
     *
     * @param string $name
     */
    public function setName($name) : void
    {
        $this->name = $name;
    }

    /**
     * Checks if the session was started.
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * @return bool
     */
    abstract public function commit(): bool;

    /**
     * @return bool
     */
    abstract public function destroy(): bool;
}