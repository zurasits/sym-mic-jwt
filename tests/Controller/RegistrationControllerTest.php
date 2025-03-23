<?php

namespace App\Tests\Controller;

use App\Controller\RegistrationController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistrationControllerTest extends KernelTestCase
{
    private $entityManager;
    private $passwordHasher;
    private $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
    }

    public function testRegisterSuccess(): void
    {
        $data = ['email' => 'test@example.com', 'password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($data));

        $hashedPassword = 'hashed_password';
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([])); // Keine Validierungsfehler

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->willReturn(false);

        $controller = new RegistrationController($this->entityManager, $this->passwordHasher, $this->validator);
        $controller->setContainer($container);

        $response = $controller->register($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('User registered successfully', $responseData['message']);
    }


    public function testRegisterMissingFields(): void
    {
        $data = ['password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($data));

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->willReturn(false);

        $controller = new RegistrationController($this->entityManager, $this->passwordHasher, $this->validator);
        $controller->setContainer($container);

        $response = $controller->register($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Email and password are required', $responseData['error']);

        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->validator->expects($this->never())->method('validate');
        $this->entityManager->expects($this->never())->method('persist');
    }

    public function testRegisterValidationErrors(): void
    {
        $data = ['email' => 'invalid-email', 'password' => 'short'];
        $request = new Request([], [], [], [], [], [], json_encode($data));

        $hashedPassword = 'hashed_password';
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $errors = new ConstraintViolationList([
            new ConstraintViolation('Invalid email format', null, [], null, 'email', 'invalid-email'),
            new ConstraintViolation('Password too short', null, [], null, 'password', 'short'),
        ]);
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($errors);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->willReturn(false);

        $controller = new RegistrationController($this->entityManager, $this->passwordHasher, $this->validator);
        $controller->setContainer($container);

        $response = $controller->register($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertCount(2, $responseData['errors']);
        $this->assertContains('Invalid email format', $responseData['errors']);
        $this->assertContains('Password too short', $responseData['errors']);

        $this->entityManager->expects($this->never())->method('persist');
    }

    public function testRegisterInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], '{invalid json');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->willReturn(false);

        $controller = new RegistrationController($this->entityManager, $this->passwordHasher, $this->validator);
        $controller->setContainer($container);

        $response = $controller->register($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid JSON data', $responseData['error']);
    }
}