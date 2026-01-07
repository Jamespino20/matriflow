<?php
declare(strict_types=1);

final class AuditLogger
{
    public static function log(?int $userId, ?string $table, string $operation, ?int $recordId = null, ?string $changes = null): void
    {
        try {
            $sql = "INSERT INTO audit_log (user_id, table_name, operation, record_id, changes_made, ip_address)
              VALUES (:user_id, :table_name, :operation, :record_id, :changes_made, :ip)";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':table_name' => $table,
                ':operation' => $operation,
                ':record_id' => $recordId,
                ':changes_made' => $changes,
                ':ip' => client_ip(),
            ]);
        } catch (Throwable $e) {
            // Don't break auth flow because logging failed on free hosting.
        }
    }
}
