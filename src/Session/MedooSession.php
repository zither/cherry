<?php
namespace Cherry\Session;

use Medoo\Medoo;

class MedooSession extends AbstractSession
{
    /**
     * @var Medoo
     */
    protected $client;

    /**
     * @var string
     */
    protected $name = 'SID';

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
    protected $token = '';

    /**
     * @var string
     */
    protected $table = 'sessions';

    /**
     * @var string
     */
    protected $keyColumn = 'name';

    /**
     * @var string
     */
    protected $valueColumn = 'content';

    public function __construct(Medoo $client, array $configs = [])
    {
        $this->client = $client;
        $this->table = $configs['table'] ?? $this->table;
        $this->keyColumn = $configs['key_column'] ?? $this->keyColumn;
        $this->valueColumn = $configs['value_column'] ?? $this->valueColumn;
    }

    /**
     * Starts the session storage.
     *
     * @return bool True if session started
     *
     * @throws \RuntimeException if session fails to start
     * @throws \Exception
     */
    public function start(): bool
    {
        if (empty($this->token)) {
            $this->token = $this->generateRandomToken();
            $this->client->insert($this->table, [$this->keyColumn => $this->token]);
        } else {
            $data = $this->client->get($this->table, '*', [$this->keyColumn => $this->token]);
            if (empty($data)) {
                $this->client->insert($this->table, [$this->keyColumn => $this->token]);
            } else {
                $this->data = unserialize($data[$this->valueColumn]);
            }
        }
        $this->started = true;
        return true;
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        $data = serialize($this->data);
        $this->client->update($this->table, [$this->valueColumn => $data], [$this->keyColumn => $this->token]);
        $this->token = '';
        $this->started = false;
        return true;
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $this->client->delete($this->table, [$this->keyColumn => $this->token]);
        $this->token = '';
        $this->started = false;
        return true;
    }

    /**
     * 删除其他 session 数据
     *
     * @param string $token
     * @return bool
     * @throws \Exception
     */
    public function destroySessionById(string $token)
    {
        if (empty($token)) {
            return false;
        }
        $this->client->delete($this->table, [$this->keyColumn => $token]);
        return true;
    }
}
