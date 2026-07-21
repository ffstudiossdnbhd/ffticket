<?php
declare(strict_types=1);

namespace FFTicket;

use PDO;

final class TicketRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findVisibleTicket(int $ticketId, array $user): ?array
    {
        $sql = $this->baseTicketSql() . ' WHERE t.id = :id';
        $params = ['id' => $ticketId];

        if ($user['role'] === 'staff') {
            $sql .= ' AND t.user_id = :user_id';
            $params['user_id'] = $user['id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ticket = $stmt->fetch();

        return $ticket ?: null;
    }

    public function listTickets(array $filters, array $user): array
    {
        $params = [
            'restrict_user' => $user['role'] === 'staff' ? 1 : 0,
            'current_user_id' => $user['id'],
            'status_filter' => (string)($filters['status'] ?? ''),
            'status_value' => (string)($filters['status'] ?? ''),
            'urgency_filter' => (string)($filters['urgency'] ?? ''),
            'urgency_value' => (string)($filters['urgency'] ?? ''),
            'assigned_to_enabled' => !empty($filters['assigned_to']) && ctype_digit((string)$filters['assigned_to']) ? 1 : 0,
            'assigned_to' => !empty($filters['assigned_to']) && ctype_digit((string)$filters['assigned_to']) ? (int)$filters['assigned_to'] : 0,
            'user_id_enabled' => !empty($filters['user_id']) && ctype_digit((string)$filters['user_id']) ? 1 : 0,
            'user_id' => !empty($filters['user_id']) && ctype_digit((string)$filters['user_id']) ? (int)$filters['user_id'] : 0,
            'search_filter' => trim((string)($filters['search'] ?? '')),
            'search_ticket' => trim((string)($filters['search'] ?? '')),
            'search_subject' => trim((string)($filters['search'] ?? '')),
        ];

        $sql = $this->baseTicketSql() . '
            WHERE (:restrict_user = 0 OR t.user_id = :current_user_id)
              AND (:status_filter = "" OR t.status = :status_value)
              AND (:urgency_filter = "" OR t.urgency = :urgency_value)
              AND (:assigned_to_enabled = 0 OR t.assigned_to = :assigned_to)
              AND (:user_id_enabled = 0 OR t.user_id = :user_id)
              AND (:search_filter = "" OR t.ticket_number LIKE CONCAT("%", :search_ticket, "%") OR t.subject LIKE CONCAT("%", :search_subject, "%"))
            ORDER BY t.created_at DESC
            LIMIT 250';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function baseTicketSql(): string
    {
        return 'SELECT t.id, t.ticket_number, t.user_id, t.assigned_to, t.category_id, t.subject,
            t.description, t.status, t.urgency, t.created_at, t.updated_at, t.closed_at,
            creator.name AS creator_name, assignee.name AS assignee_name, c.name AS category_name
            FROM tickets t
            INNER JOIN users creator ON creator.id = t.user_id
            LEFT JOIN users assignee ON assignee.id = t.assigned_to
            INNER JOIN categories c ON c.id = t.category_id';
    }
}
