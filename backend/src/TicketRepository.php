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

        return $ticket ? $this->normalizeTicket($ticket) : null;
    }

    public function listTickets(array $filters, array $user): array
    {
        $where = [];
        $params = [];

        if ($user['role'] === 'staff') {
            $where[] = 't.user_id = :current_user_id';
            $params['current_user_id'] = (int)$user['id'];
            $params['comment_reader_user_id'] = (int)$user['id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 't.status = :status';
            $params['status'] = (string)$filters['status'];
        }

        if (!empty($filters['urgency_type_id']) && ctype_digit((string)$filters['urgency_type_id'])) {
            $where[] = 't.urgency_type_id = :urgency_type_id';
            $params['urgency_type_id'] = (int)$filters['urgency_type_id'];
        } elseif (!empty($filters['urgency'])) {
            $where[] = 'u.name = :urgency';
            $params['urgency'] = (string)$filters['urgency'];
        }

        if (!empty($filters['assigned_to']) && ctype_digit((string)$filters['assigned_to'])) {
            $where[] = 't.assigned_to = :assigned_to';
            $params['assigned_to'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['user_id']) && ctype_digit((string)$filters['user_id'])) {
            $where[] = 't.user_id = :user_id';
            $params['user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $where[] = 't.created_at >= :from_date AND t.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)';
            $params['from_date'] = (string)$filters['from'];
            $params['to_date'] = (string)$filters['to'];
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(t.ticket_number LIKE :search_ticket OR t.subject LIKE :search_subject)';
            $params['search_ticket'] = '%' . $search . '%';
            $params['search_subject'] = '%' . $search . '%';
        }

        $sql = $this->baseTicketSql($user['role'] === 'staff');
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 250';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map($this->normalizeTicket(...), $stmt->fetchAll());
    }

    public function baseTicketSql(bool $includeUnreadTechComments = false): string
    {
        $unreadComments = !$includeUnreadTechComments
            ? '0 AS has_unread_tech_comments'
            : "CASE WHEN EXISTS (
                    SELECT 1
                    FROM ticket_comments unread_comment
                    INNER JOIN users unread_author ON unread_author.id = unread_comment.created_by
                    LEFT JOIN ticket_comment_reads comment_read
                        ON comment_read.ticket_id = t.id AND comment_read.user_id = :comment_reader_user_id
                    WHERE unread_comment.ticket_id = t.id
                      AND unread_author.role IN ('admin', 'it_staff')
                      AND unread_comment.id > COALESCE(comment_read.last_read_comment_id, 0)
                ) THEN 1 ELSE 0 END AS has_unread_tech_comments";

        return 'SELECT t.id, t.ticket_number, t.user_id, t.assigned_to, t.category_id,
            t.urgency_type_id, t.location_id, t.subject, t.description, t.status,
            COALESCE(u.name, \'\') AS urgency, l.name AS location_name, t.created_at, t.updated_at, t.closed_at,
            creator.name AS creator_name, COALESCE(NULLIF(assignee.nickname, ''), assignee.name) AS assignee_name, c.name AS category_name, ' . $unreadComments . '
            FROM tickets t
            INNER JOIN users creator ON creator.id = t.user_id
            LEFT JOIN users assignee ON assignee.id = t.assigned_to
            INNER JOIN categories c ON c.id = t.category_id
            LEFT JOIN urgency_types u ON u.id = t.urgency_type_id
            INNER JOIN locations l ON l.id = t.location_id';
    }

    private function normalizeTicket(array $ticket): array
    {
        $ticket['has_unread_tech_comments'] = (bool)($ticket['has_unread_tech_comments'] ?? false);

        return $ticket;
    }
}
