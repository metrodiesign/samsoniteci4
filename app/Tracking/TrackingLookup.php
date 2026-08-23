<?php

namespace App\Tracking;

use CodeIgniter\Database\BaseConnection;

final class TrackingLookup
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @return list<array{status_id: int, status_name: string, status_name_th: string, occurred_at: string}> */
    public function timeline(string $trackId): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $trackId) !== 1) {
            return [];
        }
        if ($this->db->table('request_order')->where('trackID', $trackId)->countAllResults() !== 1) {
            return [];
        }

        $logs = $this->db->table('status_log')
            ->select(['action_id', 'update_id', 'cdate'])
            ->where('order_id', $trackId)
            ->orderBy('cdate', 'DESC')
            ->get()
            ->getResultArray();
        $statusIds = [];
        $occurredAt = [];
        foreach ($logs as $log) {
            $statusId = $log['action_id'] === null
                ? (in_array((int) $log['update_id'], [9, 11, 24, 33], true) ? 6 : 3)
                : (int) $log['action_id'];
            $statusIds[$statusId] = $statusId;
            $occurredAt[$statusId] ??= (string) $log['cdate'];
        }
        if ($statusIds === []) {
            return [];
        }

        $statuses = $this->db->table('statusaction')
            ->select(['status_id', 'status_name', 'status_name_th'])
            ->whereIn('status_id', array_values($statusIds))
            ->orderBy('status_id', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $status): array => [
                'status_id'      => (int) $status['status_id'],
                'status_name'    => (string) $status['status_name'],
                'status_name_th' => (string) $status['status_name_th'],
                'occurred_at'    => $occurredAt[(int) $status['status_id']],
            ],
            $statuses,
        );
    }
}
