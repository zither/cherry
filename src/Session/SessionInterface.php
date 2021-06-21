<?php
namespace Cherry\Session;

interface SessionInterface
{
    /**
     * Starts the session storage.
     *
     * @return bool True if session started
     *
     * @throws \RuntimeException if session fails to start
     * @throws \Exception
     */
    public function start();

    /**
     * Returns the session ID.
     *
     * @return string The session ID
     * @throws \Exception
     */
    public function getId();


    /**
     * Sets the session ID.
     *
     * @param string $id
     */
    public function setId($id);

    /**
     * Returns the session name.
     *
     * @return mixed The session name
     */
    public function getName(): string;


    /**
     * Sets the session name.
     *
     * @param string $name
     */
    public function setName($name): void;

    /**
     * Checks if the session was started.
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * @return bool
     */
    public function commit();

    /**
     * @return bool
     */
    public function destroy();
}
