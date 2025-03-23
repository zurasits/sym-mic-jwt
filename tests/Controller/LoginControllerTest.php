<?php

namespace App\Tests\Controller;

use App\Controller\LoginController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginControllerTest extends KernelTestCase
{
    private $entityManager;
    private $passwordHasher;
    private $jwtManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
    }

    public function testValidLogin(): void
    {
        $requestData = ['email' => 'test@example.com', 'password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = new User();
        $user->setEmail($requestData['email']);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $requestData['email']])
            ->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $requestData['password'])
            ->willReturn(true);

        $token = 'mock.jwt.token';
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn($token);

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertEquals($token, $responseData['token']);
    }

    public function testLoginMissingFields(): void
    {
        $requestData = ['password' => 'password123']; // Kein Email
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Email and password are required', $responseData['error']);

        $this->entityManager->expects($this->never())->method('getRepository');
    }

    public function testLoginInvalidCredentials(): void
    {
        $requestData = ['email' => 'test@example.com', 'password' => 'wrongpass'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = new User();
        $user->setEmail($requestData['email']);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $requestData['email']])
            ->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $requestData['password'])
            ->willReturn(false);

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid credentials', $responseData['error']);

        $this->jwtManager->expects($this->never())->method('create');
    }

    public function testLoginUserNotFound(): void
    {
        $requestData = ['email' => 'nonexistent@example.com', 'password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $requestData['email']])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid credentials', $responseData['error']);

        $this->passwordHasher->expects($this->never())->method('isPasswordValid');
    }

    public function testLoginJwtTokenCreationFailure(): void
    {
        $requestData = ['email' => 'test@example.com', 'password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = new User();
        $user->setEmail($requestData['email']);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $requestData['email']])
            ->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $requestData['password'])
            ->willReturn(true);

        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willThrowException(new \Exception('JWT creation failed'));

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('JWT Token could not be created', $responseData['error']);
    }

    public function testLoginInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], '{invalid json');

        $controller = new LoginController($this->jwtManager, $this->passwordHasher, $this->entityManager);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid JSON data', $responseData['error']);
    }
}