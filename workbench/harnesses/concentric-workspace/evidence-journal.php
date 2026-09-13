<?php

declare(strict_types=1);

final class CMSA_Concentric_Evidence_Journal
{
    /** @var array<string,mixed> */
    private array $task;
    /** @var array<int,array<string,mixed>> */
    private array $events = [];
    private string $status = 'planned';
    private bool $beforeCaptured = false;
    private bool $executed = false;
    private bool $verified = false;

    /** @param array<string,mixed> $task */
    public function __construct(array $task)
    {
        $id = trim((string)($task['id'] ?? ''));
        $operation = trim((string)($task['operation'] ?? ''));
        if ($id === '' || $operation === '') {
            throw new InvalidArgumentException('Task id and operation are required.');
        }
        $this->task = [
            'id' => $id,
            'operation' => $operation,
            'mutating' => ($task['mutating'] ?? false) === true,
        ];
        $this->append('planned', ['operation' => $operation, 'mutating' => $this->task['mutating']]);
    }

    /** @param array<string,mixed> $before */
    public function captureBefore(array $before): void
    {
        if ($this->executed) {
            throw new LogicException('Before-state cannot be captured after execution.');
        }
        $this->beforeCaptured = true;
        $this->status = 'before_captured';
        $this->append('before_captured', ['state_hash' => self::stateHash($before)]);
    }

    public function recordRoute(int $ring, string $executor): void
    {
        if ($ring < 1 || $ring > 5 || trim($executor) === '') {
            throw new InvalidArgumentException('A valid ring and executor are required.');
        }
        $this->status = 'routed';
        $this->append('routed', ['ring' => $ring, 'executor' => $executor]);
    }

    /** @param array<string,mixed> $result */
    public function recordExecution(array $result): void
    {
        if ($this->task['mutating'] && !$this->beforeCaptured) {
            throw new LogicException('Mutating tasks require before-state capture before execution.');
        }
        if ($this->executed) {
            throw new LogicException('Execution may only be recorded once.');
        }
        $this->executed = true;
        $this->status = 'executed';
        $this->append('executed', ['result_hash' => self::stateHash($result)]);
    }

    /** @param array<string,mixed> $after */
    public function verify(bool $passed, array $after): void
    {
        if (!$this->executed) {
            throw new LogicException('Verification requires a recorded execution.');
        }
        if ($this->verified) {
            throw new LogicException('Verification may only be recorded once.');
        }
        $this->verified = true;
        $this->status = $passed ? 'complete' : ($this->task['mutating'] ? 'needs_rollback' : 'failed');
        $this->append('verified', [
            'passed' => $passed,
            'after_hash' => self::stateHash($after),
            'resulting_status' => $this->status,
        ]);
    }

    /** @param array<string,mixed> $restored */
    public function recordRollback(array $restored): void
    {
        if ($this->status !== 'needs_rollback') {
            throw new LogicException('Rollback is only valid after failed verification of a mutating task.');
        }
        $this->status = 'rolled_back';
        $this->append('rolled_back', ['restored_hash' => self::stateHash($restored)]);
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return array<int,array<string,mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    public function verifyChain(): bool
    {
        $previous = 'GENESIS';
        foreach ($this->events as $event) {
            $payload = $event;
            $hash = (string)($payload['hash'] ?? '');
            unset($payload['hash']);
            if (($payload['previous_hash'] ?? null) !== $previous) {
                return false;
            }
            if (!hash_equals($hash, self::eventHash($payload))) {
                return false;
            }
            $previous = $hash;
        }
        return true;
    }

    /** @return array<string,mixed> */
    public function export(): array
    {
        return [
            'task' => $this->task,
            'status' => $this->status,
            'events' => $this->events,
            'chain_valid' => $this->verifyChain(),
        ];
    }

    /** Test-only tamper hook used by the workbench red-team probe. */
    public function tamperForProbe(int $index, string $key, mixed $value): void
    {
        if (!isset($this->events[$index])) {
            throw new OutOfBoundsException('Unknown event index.');
        }
        $this->events[$index][$key] = $value;
    }

    /** @param array<string,mixed> $payload */
    private function append(string $type, array $payload): void
    {
        $previous = empty($this->events) ? 'GENESIS' : (string)$this->events[array_key_last($this->events)]['hash'];
        $event = [
            'sequence' => count($this->events) + 1,
            'type' => $type,
            'previous_hash' => $previous,
            'payload' => $payload,
        ];
        $event['hash'] = self::eventHash($event);
        $this->events[] = $event;
    }

    /** @param array<string,mixed> $event */
    private static function eventHash(array $event): string
    {
        return hash('sha256', self::canonicalJson($event));
    }

    /** @param array<string,mixed> $state */
    private static function stateHash(array $state): string
    {
        return hash('sha256', self::canonicalJson($state));
    }

    /** @param array<string,mixed> $value */
    private static function canonicalJson(array $value): string
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::sortRecursive($item);
            }
        }
        unset($item);
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<mixed> $value @return array<mixed> */
    private static function sortRecursive(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::sortRecursive($item);
            }
        }
        unset($item);
        return $value;
    }
}
