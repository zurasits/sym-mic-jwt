<?php

namespace App\Tests\Controller;

use App\Controller\LoginController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface as UserPasswordHasherInterfaceAlias;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class LoginControllerTest extends KernelTestCase
{

    private $entityManager;
    private $passwordHasher;
    private $client;
    private $jwtManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterfaceAlias::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        self::bootKernel();
        $container = self::getContainer();
        /** @var ?HttpClientInterface $httpClient */
        $httpClient = $container->get(HttpClientInterface::class);
        $this->client = $httpClient;
    }


    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testValidLogin(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

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

        $controller = new LoginController($this->jwtManager,  $this->passwordHasher, $this->entityManager,);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertEquals($token, $responseData['token']);
    }

    /**
     * @dataProvider loginDataProvider
     * @param $email
     * @param $password
     * @param $expectedStatusCode
     * @param null $error
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function testInvalidLogin($email, $password, $expectedStatusCode, $error = null): void
    {
        $this->expectException(\Symfony\Component\HttpClient\Exception\ClientException::class);
        $response = $this->client->request(
            'POST',
            'https://sym-mic-jwt.ddev.site/api/login',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['email' => $email, 'password' => $password])
            ]
        );

        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        $responseArray = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseArray);
        $this->assertEquals($error, $responseArray['error']);


    }

    public function loginDataProvider(): array
    {
        return [
            // Test 1: Email and/or Password not provided
            ['email' => null, 'password' => null, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            ['email' => null, 'password' => 1234, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            ['email' => 'zurasits@gmail.com', 'password' => null, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            // Test 2: wrong data
            ['email' => 'zurasits@gmail.com', 'password' => 'wrongpassword', 'expectedStatusCode' => 401, 'error' => 'Invalid credentials'],
            ['email' => 'wrong@email.com', 'password' => 'test.1977', 'expectedStatusCode' => 401, 'error' => 'Invalid credentials'],
        ];
    }


    public function testLoginJwtTokenCreationFailure(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];
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

        $controller = new LoginController($this->jwtManager,  $this->passwordHasher, $this->entityManager,);
        $response = $controller->login($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('JWT Token could not be created', $responseData['error']);
    }
}
