<?php

namespace App\Authentication;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Encryption\EncrypterInterface;
use DateTimeImmutable;

final class ResetRequestWorkflow
{
    private ShadowUserStore $users;

    private ResetTokenFactory $tokenFactory;

    private ResetTokenStore $tokens;

    public function __construct(
        private BaseConnection $db,
        private EncrypterInterface $encrypter,
        ?ResetTokenFactory $tokenFactory = null,
    ) {
        $this->users        = new ShadowUserStore($db);
        $this->tokenFactory = $tokenFactory ?? new ResetTokenFactory();
        $this->tokens       = new ResetTokenStore($db, $this->tokenFactory);
    }

    public function request(string $email, DateTimeImmutable $now): void
    {
        $userId = $this->users->findActiveIdByEmail($email);

        if ($userId === null) {
            return;
        }

        $recipient = strtolower(trim($email));
        $issued    = $this->tokenFactory->issue($now);

        $this->tokens->issue(
            $userId,
            $issued,
            function (BaseConnection $db) use ($userId, $recipient, $issued): void {
                (new ResetDeliveryIntentStore($db, $this->encrypter))->enqueue(
                    $userId,
                    $recipient,
                    $issued,
                );
            },
        );
    }
}
