<?php

namespace App\Rating;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RatingWorkflow
{
    public function __construct(private BaseConnection $db)
    {
    }

    /** @param list<int> $scores */
    public function submit(
        int $requestId,
        string $trackId,
        array $scores,
        string $comment,
        ?DateTimeImmutable $now = null,
    ): string {
        if (
            $requestId < 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $trackId) !== 1
            || count($scores) !== 8
            || array_filter($scores, static fn (int $score): bool => $score < 1 || $score > 5) !== []
            || mb_strlen($comment) > 2000
        ) {
            throw new InvalidArgumentException('Invalid rating.');
        }

        $order = $this->db->table('request_order')
            ->select(['request_id', 'branchID', 'action_status'])
            ->where('request_id', $requestId)
            ->where('trackID', $trackId)
            ->get()
            ->getRowArray();
        if ($order === null || ! in_array((int) $order['action_status'], [5, 7], true)) {
            return 'not_found';
        }

        $timestamp = ($now ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if ($this->db->table('rating')->where('order_id', $trackId)->countAllResults() !== 0) {
                $this->db->transRollback();

                return 'duplicate';
            }
            if ((int) $order['action_status'] === 5) {
                $updated = $this->db->table('request_order')
                    ->where('request_id', $requestId)
                    ->where('trackID', $trackId)
                    ->where('action_status', 5)
                    ->update([
                        'action_status'      => 7,
                        'date_complete'      => $timestamp,
                        'date_update_status' => $timestamp,
                    ]);
                if (! $updated || $this->db->affectedRows() !== 1) {
                    $this->db->transRollback();

                    return 'duplicate';
                }
            }

            $rows = [];
            foreach ($scores as $index => $score) {
                $rows[] = [
                    'add_id'   => $index + 1,
                    'rating'   => $score,
                    'order_id' => $trackId,
                    'branchID' => (int) $order['branchID'],
                    'cdate'    => $timestamp,
                ];
            }
            if (! $this->db->table('rating')->insertBatch($rows)) {
                throw new RuntimeException('Unable to store rating.');
            }
            if ($comment !== '' && ! $this->db->table('rating_comment')->insert([
                'track_id'  => $trackId,
                'branch_id' => (int) $order['branchID'],
                'comment'   => $comment,
                'created_at' => $timestamp,
            ])) {
                throw new RuntimeException('Unable to store rating comment.');
            }
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Unable to complete rating transaction.');
            }
            $this->db->transCommit();

            return 'recorded';
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
