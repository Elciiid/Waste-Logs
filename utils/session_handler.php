<?php
/**
 * utils/session_handler.php
 * Custom PDO-based session handler for stateless environments like Vercel.
 */

class PdoSessionHandler implements SessionHandlerInterface
{
    private $pdo;
    private $table = 'app_sessions';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string|false
    {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool
    {
        $timestamp = time();
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (id, data, timestamp)
            VALUES (?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, timestamp = EXCLUDED.timestamp
        ");
        return $stmt->execute([$id, $data, $timestamp]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($maxlifetime): int|false
    {
        $old = time() - $maxlifetime;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE timestamp < ?");
        $stmt->execute([$old]);
        return $stmt->rowCount();
    }
}
?>
