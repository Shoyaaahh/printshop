<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = (string)env('DB_PATH', STORAGE . '/printshop.sqlite');
        if (!is_file($path) && PHP_SAPI !== 'cli') {
            http_response_code(500);
            exit('Database not found. Run: php database/install.php');
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

/** Run a prepared statement (always parameterised: protects against SQL injection). */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function fetch_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function fetch_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function fetch_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int)db()->lastInsertId();
}
function update_row(string $table, int $id, array $data, string $idCol = 'id'): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE $idCol = ?", [...array_values($data), $id]);
}
function transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
