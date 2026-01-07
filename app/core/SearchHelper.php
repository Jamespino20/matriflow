<?php
declare(strict_types=1);

final class SearchHelper
{
    /**
     * Builds a WHERE clause and params for PDO based on provided filters.
     * 
     * @param array $filters Key-value pairs of filters (e.g., ['username' => 'james', 'role' => 'admin'])
     * @param array $searchableColumns Columns to perform LIKE %value% search on
     * @return array [string $sqlPart, array $params]
     */
    public static function buildWhere(array $filters, array $searchableColumns = []): array
    {
        $sql = [];
        $params = [];

        // Keyword Search (OR across searchable columns)
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $searchParts = [];
            foreach ($searchableColumns as $col) {
                $paramName = "q_" . str_replace('.', '_', $col);
                $searchParts[] = "$col LIKE :$paramName";
                $params[":$paramName"] = "%$q%";
            }
            if ($searchParts) {
                $sql[] = "(" . implode(" OR ", $searchParts) . ")";
            }
        }

        // Exact Match Filters
        foreach ($filters as $key => $val) {
            if ($key === 'q' || $val === '' || $val === null) continue;
            
            // Handle specialized date filters
            if ($key === 'date_from') {
                $sql[] = "created_at >= :date_from";
                $params[':date_from'] = $val;
            } elseif ($key === 'date_to') {
                $sql[] = "created_at <= :date_to";
                $params[':date_to'] = $val . " 23:59:59";
            } else {
                // Default exact match (sanitizing key to prevent injection)
                $safeKey = preg_replace('/[^a-zA-Z0-9_.]/', '', $key);
                $paramKey = ":" . str_replace('.', '_', $safeKey);
                $sql[] = "$safeKey = $paramKey";
                $params[$paramKey] = $val;
            }
        }

        $sqlPart = $sql ? " WHERE " . implode(" AND ", $sql) : "";
        return [$sqlPart, $params];
    }
}
