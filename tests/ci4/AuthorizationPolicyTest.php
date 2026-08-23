<?php

namespace Tests\Ci4;

use App\Authorization\AuthorizationPolicy;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthorizationPolicyTest extends CIUnitTestCase
{
    public function testConfirmedRoleActionMatrix(): void
    {
        $policy = new AuthorizationPolicy();

        self::assertTrue($policy->allowsAction(1, 'read'));
        self::assertTrue($policy->allowsAction(1, 'write'));
        self::assertTrue($policy->allowsAction(1, 'delete'));
        self::assertTrue($policy->allowsAction(2, 'read'));
        self::assertTrue($policy->allowsAction(2, 'write'));
        self::assertTrue($policy->allowsAction(2, 'delete'));
        self::assertTrue($policy->allowsAction(3, 'read'));
        self::assertFalse($policy->allowsAction(3, 'write'));
        self::assertFalse($policy->allowsAction(3, 'delete'));
        self::assertFalse($policy->allowsAction(null, 'read'));
        self::assertFalse($policy->allowsAction(99, 'read'));
        self::assertFalse($policy->allowsAction(1, 'unknown'));
    }

    public function testConfirmedBranchOwnershipMatrix(): void
    {
        $policy = new AuthorizationPolicy();

        self::assertTrue($policy->allowsBranch(1, null, 2));
        self::assertFalse($policy->allowsBranch(1, null, null));
        self::assertTrue($policy->allowsBranch(2, 1, 1));
        self::assertFalse($policy->allowsBranch(2, 1, 2));
        self::assertTrue($policy->allowsBranch(3, '1', '1'));
        self::assertFalse($policy->allowsBranch(3, 1, 2));
        self::assertFalse($policy->allowsBranch(2, null, 1));
        self::assertFalse($policy->allowsBranch(2, 1, null));
        self::assertFalse($policy->allowsBranch(99, 1, 1));
    }

    public function testCrossBranchGuardUsesNotFoundResponse(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);

        (new AuthorizationPolicy())->assertBranchAccess(2, 1, 2);
    }
}
