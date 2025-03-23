<?php

namespace App\Controller;

use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class RefreshTokenController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }


    public function refreshToken(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof UserInterface) {
            return new JsonResponse(['error' => 'Invalid user'], 401);
        }

        try {
            $newToken = $this->jwtManager->create($user);
        } catch (Exception) {
            return new JsonResponse(['error' => 'Could not refresh token'], 500);
        }

        return new JsonResponse(['refresh-token' => $newToken]);
    }
}