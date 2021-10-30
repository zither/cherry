<?php

namespace Cherry\Test;

use Cherry\Session\SessionInterface;
use Cherry\Test\Traits\SetupCherryEnv;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ServerRequestInterface;

class TestCase extends PHPUnitTestCase
{
    use SetupCherryEnv;

    public function setUp(): void
    {
        $this->setUpCherryEnv();
    }

    public function tearDown(): void
    {
        $this->tearDownCherryEnv();
    }

    protected function getNextMessagesByTypeFromSession(SessionInterface $session, string $type): array
    {
        return $session['MemoFlashMessages']['forNext'][$type] ?? [];
    }

    protected function signIn(ServerRequestInterface $request): SessionInterface
    {
        $session = $this->container->make(SessionInterface::class, ['request' => $request]);
        $session['is_admin'] = 1;
        return $session;
    }
}
