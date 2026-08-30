<?php

namespace Tests\Ci4;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class RatingHttpTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->db->table('request_order')->insert([
            'request_id'   => 91005,
            'trackID'      => 'WP00C-RATE-005',
            'branchID'     => 1,
            'action_status' => 5,
        ]);
    }

    public function testValidStatusFiveOrderRendersEightQuestionRatingForm(): void
    {
        $result = $this->get('/rating/WP00C-RATE-005');

        $result->assertStatus(200);
        self::assertStringContainsString('Service rating', (string) $result->getBody());
        self::assertStringContainsString('WP00C-RATE-005', (string) $result->getBody());
        for ($question = 1; $question <= 8; $question++) {
            self::assertStringContainsString('name="rating_' . $question . '"', $result->getBody());
        }
        self::assertStringContainsString('name="rating_comment"', $result->getBody());
    }

    public function testFirstValidRatingWritesEightScoresCommentAndCompletesOrder(): void
    {
        $result = $this->post('/rating', $this->payload());

        $result->assertStatus(201);
        $result->assertJSONExact(['status' => 'rating_recorded']);
        self::assertSame(8, $this->db->table('rating')->countAllResults());
        self::assertSame('27', (string) $this->db->table('rating')->selectSum('rating')->get()->getRow('rating'));
        self::assertSame(1, $this->db->table('rating_comment')->countAllResults());
        self::assertSame(
            'SYNTHETIC RATING COMMENT',
            $this->db->table('rating_comment')->get()->getRow('comment'),
        );
        $order = $this->db->table('request_order')->where('request_id', 91005)->get()->getRowArray();
        self::assertSame(7, (int) $order['action_status']);
        self::assertNotEmpty($order['date_complete']);
        self::assertNotEmpty($order['date_update_status']);
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testDuplicateInvalidUnknownAndTokenlessRatingsHaveNoExtraSideEffects(): void
    {
        $this->post('/rating', $this->payload())->assertStatus(201);

        $this->post('/rating', $this->payload())->assertStatus(409);
        self::assertSame(8, $this->db->table('rating')->countAllResults());
        self::assertSame(1, $this->db->table('rating_comment')->countAllResults());

        $this->db->table('request_order')->insert([
            'request_id'   => 91006,
            'trackID'      => 'WP00C-RATE-006',
            'branchID'     => 1,
            'action_status' => 5,
        ]);
        $invalid = $this->payload(91006, 'WP00C-RATE-006');
        $invalid['rating_4'] = '0';
        $this->post('/rating', $invalid)->assertStatus(422);

        $this->post('/rating', $this->payload(99999, 'WP00C-RATE-999'))->assertStatus(404);

        $tokenless = $this->payload(91006, 'WP00C-RATE-006');
        unset($tokenless['csrf_test_name']);
        try {
            $this->post('/rating', $tokenless);
            self::fail('Expected CSRF rejection.');
        } catch (SecurityException $exception) {
            self::assertSame(403, $exception->getCode());
        }

        self::assertSame(8, $this->db->table('rating')->countAllResults());
        self::assertSame(1, $this->db->table('rating_comment')->countAllResults());
        self::assertSame(5, (int) $this->db->table('request_order')
            ->where('request_id', 91006)
            ->get()
            ->getRow('action_status'));
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testStatusSevenOrderWithoutRatingStillRendersRatingForm(): void
    {
        $this->insertStatusSevenOrder();

        $result = $this->get('/rating/WP00C-RATE-007');

        $result->assertStatus(200);
        self::assertStringContainsString('WP00C-RATE-007', (string) $result->getBody());
        for ($question = 1; $question <= 8; $question++) {
            self::assertStringContainsString('name="rating_' . $question . '"', $result->getBody());
        }
    }

    public function testStatusSevenRatingRecordsWithoutTouchingStatusOrDateColumns(): void
    {
        $this->insertStatusSevenOrder();

        $result = $this->post('/rating', $this->payload(91007, 'WP00C-RATE-007'));

        $result->assertStatus(201);
        $result->assertJSONExact(['status' => 'rating_recorded']);
        self::assertSame(8, $this->db->table('rating')->countAllResults());
        self::assertSame(1, $this->db->table('rating_comment')->countAllResults());
        $order = $this->db->table('request_order')->where('request_id', 91007)->get()->getRowArray();
        self::assertSame(7, (int) $order['action_status']);
        self::assertSame('2020-01-01 00:00:00', (string) $order['date_complete']);
        self::assertSame('2020-01-01 00:00:00', (string) $order['date_update_status']);
        self::assertSame(0, $this->db->table('status_log')->countAllResults());
    }

    public function testStatusSevenRatingRejectsSecondSubmissionAsDuplicate(): void
    {
        $this->insertStatusSevenOrder();

        $this->post('/rating', $this->payload(91007, 'WP00C-RATE-007'))->assertStatus(201);
        $this->post('/rating', $this->payload(91007, 'WP00C-RATE-007'))->assertStatus(409);

        self::assertSame(8, $this->db->table('rating')->countAllResults());
        self::assertSame(1, $this->db->table('rating_comment')->countAllResults());
    }

    public function testStatusesOtherThanFiveOrSevenAreNotRatable(): void
    {
        foreach ([1, 2, 3, 4, 6, 8] as $status) {
            $requestId = 91200 + $status;
            $trackId   = 'WP00C-RATE-2' . $status;
            $this->db->table('request_order')->insert([
                'request_id'    => $requestId,
                'trackID'       => $trackId,
                'branchID'      => 1,
                'action_status' => $status,
            ]);

            try {
                $this->get('/rating/' . $trackId);
                self::fail('Expected 404 for GET on status ' . $status);
            } catch (PageNotFoundException) {
                // expected: form() rejects a non-ratable status
            }
            $this->post('/rating', $this->payload($requestId, $trackId))->assertStatus(404);
            self::assertSame(0, $this->db->table('rating')->where('order_id', $trackId)->countAllResults());
        }
    }

    private function insertStatusSevenOrder(): void
    {
        $this->db->table('request_order')->insert([
            'request_id'         => 91007,
            'trackID'            => 'WP00C-RATE-007',
            'branchID'           => 1,
            'action_status'      => 7,
            'date_complete'      => '2020-01-01 00:00:00',
            'date_update_status' => '2020-01-01 00:00:00',
        ]);
    }

    /** @return array<string, string> */
    private function payload(int $requestId = 91005, string $trackId = 'WP00C-RATE-005'): array
    {
        $payload = [
            'csrf_test_name' => service('security')->getHash(),
            'request_id'     => (string) $requestId,
            'track_id'       => $trackId,
            'rating_comment' => 'SYNTHETIC RATING COMMENT',
        ];
        foreach ([5, 4, 3, 2, 1, 5, 4, 3] as $index => $score) {
            $payload['rating_' . ($index + 1)] = (string) $score;
        }

        return $payload;
    }

    private function createTables(): void
    {
        $tables = [
            'request_order' => 'request_id INTEGER PRIMARY KEY, trackID VARCHAR(100) NOT NULL, branchID INTEGER NOT NULL, action_status INTEGER NOT NULL, date_complete DATETIME, date_update_status DATETIME',
            'rating'        => 'rating_id INTEGER PRIMARY KEY AUTOINCREMENT, add_id INTEGER NOT NULL, rating INTEGER NOT NULL, order_id VARCHAR(100) NOT NULL, branchID INTEGER NOT NULL, cdate DATETIME NOT NULL',
            'rating_comment' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, track_id VARCHAR(100) NOT NULL, branch_id INTEGER NOT NULL, comment TEXT NOT NULL, created_at DATETIME NOT NULL',
            'status_log'    => 'id INTEGER PRIMARY KEY AUTOINCREMENT, order_id VARCHAR(100) NOT NULL, action_id INTEGER, update_id INTEGER, cdate DATETIME NOT NULL',
        ];
        foreach ($tables as $table => $definition) {
            $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
            $this->db->query("DROP TABLE IF EXISTS {$name}");
            $this->db->query("CREATE TABLE {$name} ({$definition})");
        }
    }
}
