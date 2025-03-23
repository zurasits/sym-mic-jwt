<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserRepositoryTest extends TestCase
{
    private $userRepository;
    private $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->userRepository = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();

        $this->userRepository->method('getEntityManager')
            ->willReturn($this->entityManager);
    }

    public function testConstruct(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $userRepository = new UserRepository($registry);

        $this->assertInstanceOf(UserRepository::class, $userRepository);
    }

    public function testUpgradePasswordWithValidUser(): void
    {
        $user = new User();
        $newHashedPassword = 'new_hashed_password';

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userRepository->upgradePassword($user, $newHashedPassword);

        $this->assertSame($newHashedPassword, $user->getPassword());
    }

    public function testUpgradePasswordWithInvalidUser(): void
    {
        $invalidUser = $this->createMock(PasswordAuthenticatedUserInterface::class);
        $newHashedPassword = 'new_hashed_password';

        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessage(sprintf('Instances of "%s" are not supported.', get_class($invalidUser)));

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->userRepository->upgradePassword($invalidUser, $newHashedPassword);
    }
}