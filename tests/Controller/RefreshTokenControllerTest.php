<?php

namespace App\Tests\Controller;

use App\Controller\RefreshTokenController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RefreshTokenControllerTest extends KernelTestCase
{
    private $jwtManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
    }

    public function testRefreshTokenSuccess(): void
    {
        $user = $this->createMock(UserInterface::class);

        $newToken = 'new.jwt.token';
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn($newToken);

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $container->expects($this->any())
            ->method('get')
            ->with('security.token_storage')
            ->willReturn($tokenStorage);

        $controller = new RefreshTokenController($this->jwtManager);
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->refreshToken($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('refresh-token', $responseData);
        $this->assertEquals($newToken, $responseData['refresh-token']);
    }

    public function testRefreshTokenNoUser(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $container->expects($this->any())
            ->method('get')
            ->with('security.token_storage')
            ->willReturn($tokenStorage);

        $controller = new RefreshTokenController($this->jwtManager);
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->refreshToken($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Invalid user', $responseData['error']);
    }


    public function testRefreshTokenJwtCreationFailure(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willThrowException(new \Exception('JWT creation failed'));

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $container->expects($this->any())
            ->method('get')
            ->with('security.token_storage')
            ->willReturn($tokenStorage);

        $controller = new RefreshTokenController($this->jwtManager);
        $controller->setContainer($container);

        $request = new Request();

        $response = $controller->refreshToken($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Could not refresh token', $responseData['error']);
    }
}