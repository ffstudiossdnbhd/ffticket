<?php
declare(strict_types=1);

namespace FFTicket;

use PDO;

final class TicketOptionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listOptions(string $table, bool $includeInactive): array
    {
        $sql = "SELECT id, name, description, is_active FROM {$table}";
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        $rows = $this->db->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['is_active'] = (bool)$row['is_active'];
        }

        return $rows;
    }

    public function createOrReactivate(string $table, string $name, ?string $description): int
    {
        $existing = $this->db->prepare("SELECT id FROM {$table} WHERE name = :name LIMIT 1");
        $existing->execute(['name' => $name]);
        $row = $existing->fetch();

        if ($row) {
            $id = (int)$row['id'];
            $stmt = $this->db->prepare(
                "UPDATE {$table}
                 SET description = :description, is_active = 1
                 WHERE id = :id"
            );
            $stmt->execute([
                'description' => $description,
                'id' => $id,
            ]);
            return $id;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO {$table} (name, description, is_active)
             VALUES (:name, :description, 1)"
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateOption(string $table, int $id, string $name, ?string $description, bool $isActive): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$table}
             SET name = :name, description = :description, is_active = :is_active
             WHERE id = :id"
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivateOption(string $table, int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$table} SET is_active = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
