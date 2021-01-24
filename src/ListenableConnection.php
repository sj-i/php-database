<?php

namespace Emonkak\Database;

class ListenableConnection implements PDOInterface
{
    /**
     * @var PDOInterface
     */
    private $delegate;

    /**
     * @var PDOListenerInterface[]
     */
    private $listeners = [];

    public function __construct(PDOInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    public function addListaner(PDOListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        foreach ($this->listeners as $listener) {
            $listener->onBeginTransaction($this->delegate);
        }

        return $this->delegate->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        foreach ($this->listeners as $listener) {
            $listener->onCommit($this->delegate);
        }

        return $this->delegate->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->delegate->errorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->delegate->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement)
    {
        $start = microtime(true);

        $result = $this->delegate->exec($statement);

        $elapsedTime = microtime(true) - $start;

        foreach ($this->listeners as $listener) {
            $listener->onQuery($this->delegate, $statement, [], $elapsedTime);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction()
    {
        return $this->delegate->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null)
    {
        return $this->delegate->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $statement, array $options = [])
    {
        $stmt = $this->delegate->prepare($statement, $options);
        if ($stmt !== false) {
            $stmt = new ListenableStatement($this->delegate, $this->listeners, $stmt, $statement);
        }
        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        $start = microtime(true);

        $stmt = $this->delegate->query($query, $fetchMode, ...$fetchModeArgs);

        if ($stmt !== false) {
            $elapsedTime = microtime(true) - $start;

            foreach ($this->listeners as $listener) {
                $listener->onQuery($this->delegate, $query, [], $elapsedTime);
            }

            $stmt = new ListenableStatement($this->delegate, $this->listeners, $stmt, $query);
        }

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $string, int $type = \PDO::PARAM_STR)
    {
        return $this->delegate->quote($string, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        foreach ($this->listeners as $listener) {
            $listener->onRollback($this->delegate);
        }

        return $this->delegate->rollback();
    }
}
