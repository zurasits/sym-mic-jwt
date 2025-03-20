<?php

namespace App\Controller;

use App\Entity\User;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class LoginController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $entityManager;

    public function __construct(
        JWTTokenManagerInterface    $jwtManager,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface      $entityManager
    )
    {
        $this->jwtManager = $jwtManager;
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        try {
            $token = $this->jwtManager->create($user);
        }catch (Exception $e) {
            return new JsonResponse(['error' => 'JWT Token could not be created'], 500);
        }

        return new JsonResponse([
            'token' => $token
        ]);
    }


    public function protected(): JsonResponse
    {
        $user = $this->getUser();

        return new JsonResponse([
            'message' => 'You are authenticated',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}