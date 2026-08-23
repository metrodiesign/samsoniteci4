<?php

namespace App\Orders;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class OrderSequence
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function next(DateTimeImmutable $now): string
    {
        $period = $now->format('ym');
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('ci4_order_sequences'));
        $orders = $this->db->escapeIdentifiers($this->db->prefixTable('request_order'));
        $lower = 'G' . $period . '0000';
        $upper = 'G' . $period . '9999';
        if (! $this->db->transBegin()) {
            throw new RuntimeException('Unable to start order sequence transaction.');
        }
        try {
            if ($this->db->DBDriver === 'SQLite3') {
                $this->db->query(
                    "INSERT INTO {$table} (period, next_value) VALUES (?, 0)
                     ON CONFLICT(period) DO NOTHING",
                    [$period],
                );
                $stored = $this->db->table('ci4_order_sequences')
                    ->select('next_value')->where('period', $period)->get()->getRow('next_value');
                $legacy = $this->db->query(
                    "SELECT trackID FROM {$orders}
                     WHERE trackID >= ? AND trackID <= ? AND trackID GLOB ?
                     ORDER BY trackID DESC LIMIT 1",
                    [$lower, $upper, 'G' . $period . '[0-9][0-9][0-9][0-9]'],
                )->getRow('trackID');
            } else {
                $this->db->query(
                    "INSERT INTO {$table} (`period`, `next_value`) VALUES (?, 0)
                     ON DUPLICATE KEY UPDATE `period` = VALUES(`period`)",
                    [$period],
                );
                $stored = $this->db->query(
                    "SELECT `next_value` FROM {$table} WHERE `period` = ? FOR UPDATE",
                    [$period],
                )->getRow('next_value');
                $legacy = $this->db->query(
                    "SELECT `trackID` FROM {$orders}
                     WHERE `trackID` >= ? AND `trackID` <= ? AND `trackID` REGEXP ?
                     ORDER BY `trackID` DESC LIMIT 1 FOR UPDATE",
                    [$lower, $upper, '^G' . $period . '[0-9]{4}$'],
                )->getRow('trackID');
            }
            $current = is_numeric($stored) ? (int) $stored : -1;
            $legacyValue = is_string($legacy) ? (int) substr($legacy, -4) : 0;
            $next = max($current + 1, $legacyValue + 1);
            if ($current < 0 || $next > 9999) {
                throw new RuntimeException('Order sequence exhausted at ' . $next . '.');
            }
            $updated = $this->db->table('ci4_order_sequences')
                ->where('period', $period)->where('next_value', $current)->update(['next_value' => $next]);
            if (! $updated || $this->db->affectedRows() !== 1
                || ! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new RuntimeException('Unable to allocate order sequence.');
            }

            return 'G' . $period . sprintf('%04d', $next);
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
