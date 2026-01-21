<?php

declare(strict_types=1);

/**
 * HmoProvider Model
 * 
 * Represents an accredited HMO provider for billing claims.
 */
final class HmoProvider
{
    /**
     * Get all active HMO providers.
     */
    public static function listActive(): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM hmo_providers WHERE is_active = 1 ORDER BY short_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get all HMO providers (including inactive).
     */
    public static function listAll(): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM hmo_providers ORDER BY short_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find an HMO provider by ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM hmo_providers WHERE hmo_provider_id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find an HMO provider by short code.
     */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM hmo_providers WHERE short_code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new HMO provider.
     */
    public static function create(string $name, string $shortName, ?string $shortCode = null): int
    {
        $code = $shortCode ?? self::generateCode();
        $stmt = Database::getInstance()->prepare("INSERT INTO hmo_providers (short_code, short_name, name) VALUES (?, ?, ?)");
        $stmt->execute([$code, $shortName, $name]);
        return (int)Database::getInstance()->lastInsertId();
    }

    /**
     * Toggle active status.
     */
    public static function toggleActive(int $id): bool
    {
        $stmt = Database::getInstance()->prepare("UPDATE hmo_providers SET is_active = NOT is_active WHERE hmo_provider_id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generate a unique short code for a new provider.
     */
    private static function generateCode(): string
    {
        $stmt = Database::getInstance()->query("SELECT MAX(hmo_provider_id) FROM hmo_providers");
        $maxId = (int)$stmt->fetchColumn();
        return 'HMO' . str_pad((string)($maxId + 1), 2, '0', STR_PAD_LEFT);
    }
}
