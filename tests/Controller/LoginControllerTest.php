<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface as EntityManagerInterfaceAlias;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\PasswordHasherInterface as PasswordHasherInterfaceAlias;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class LoginControllerTest extends KernelTestCase
{

    private $entityManagerMock;
    private $passwordHasherMock;
    private HttpClientInterface $client;

    protected function setUp(): void
    {

        $this->entityManagerMock = $this->createMock(EntityManagerInterfaceAlias::class);
        $this->passwordHasherMock = $this->createMock(PasswordHasherInterfaceAlias::class);
        self::bootKernel();
        $container = self::getContainer();
        /** @var ?HttpClientInterface $httpClient */
        $httpClient = $container->get(HttpClientInterface::class);
        $this->client = $httpClient;
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testValidLogin(): void
    {



        $email = 'zurasits@gmail.com';
        $password = 'test.1977';


        $response = $this->client->request(
            'POST',
            'https://sym-mic-jwt.ddev.site/api/login',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode(['email' => $email, 'password' => $password])
            ]
        );


        $this->assertEquals(200, $response->getStatusCode());
        $response = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);

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
            // Test 1: Email und Passwort fehlen
            ['email' => null, 'password' => null, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            ['email' => null, 'password' => 1234, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            ['email' => 'zurasits@gmail.com', 'password' => null, 'expectedStatusCode' => 400, 'error' => 'Email and password are required'],
            // Test 2: wrong data
            ['email' => 'zurasits@gmail.com', 'password' => 'wrongpassword', 'expectedStatusCode' => 401, 'error' => 'Invalid credentials'],
            ['email' => 'wrong@email.com', 'password' => 'test.1977', 'expectedStatusCode' => 401, 'error' => 'Invalid credentials'],

            // Test 5: Fehler bei der Token-Erstellung (Simuliert einen Fehler)
            // ('email' => 'zurasits@gmail.com', 'password' => 'test.1977', 'expectedStatusCode' => 500, 'expectedError' => 'JWT Token could not be created'),
        ];
    }


}
