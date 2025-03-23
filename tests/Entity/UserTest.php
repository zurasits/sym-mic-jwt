<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testGetId(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testEmail(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);

        $this->assertSame($email, $this->user->getEmail());
        $this->assertInstanceOf(User::class, $this->user->setEmail($email));
    }

    public function testGetUserIdentifier(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);

        $this->assertSame($email, $this->user->getUserIdentifier());
    }

    public function testRoles(): void
    {
        $this->assertEquals(['ROLE_USER'], $this->user->getRoles());

        $roles = ['ROLE_ADMIN', 'ROLE_USER'];
        $this->user->setRoles($roles);

        $this->assertSame($roles, $this->user->getRoles());
        $this->assertInstanceOf(User::class, $this->user->setRoles($roles));

        $this->user->setRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);
        $this->assertEquals(['ROLE_ADMIN', 'ROLE_USER'], $this->user->getRoles());
    }

    public function testPassword(): void
    {
        $password = 'hashed_password';
        $this->user->setPassword($password);

        $this->assertSame($password, $this->user->getPassword());
        $this->assertInstanceOf(User::class, $this->user->setPassword($password));
    }

    public function testEraseCredentials(): void
    {
        $this->user->eraseCredentials();
        $this->assertTrue(true);
    }
}