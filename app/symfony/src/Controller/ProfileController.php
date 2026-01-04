<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/update', name: 'app_profile_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $data = $request->request->all();

        // Mettre à jour les informations de l'utilisateur
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        if (isset($data['betting_profile'])) {
            $user->setBettingProfile($data['betting_profile']);
        }

        if (isset($data['bankroll'])) {
            $user->setBankroll((float) $data['bankroll']);
        }

        if (isset($data['max_stake_percentage'])) {
            $user->setMaxStakePercentage((float) $data['max_stake_percentage']);
        }

        // Changer le mot de passe si fourni
        if (!empty($data['new_password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['new_password']);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Profil mis à jour avec succès');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/api/profile', name: 'api_profile_get', methods: ['GET'])]
    public function getProfile(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'betting_profile' => $user->getBettingProfile(),
            'bankroll' => $user->getBankroll(),
            'initial_bankroll' => $user->getInitialBankroll(),
            'max_stake_percentage' => $user->getMaxStakePercentage(),
            'bankroll_growth' => $user->getBankrollGrowth(),
            'max_stake_amount' => $user->getMaxStakeAmount(),
        ]);
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PUT', 'PATCH'])]
    public function updateProfileApi(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['betting_profile'])) {
            $user->setBettingProfile($data['betting_profile']);
        }

        if (isset($data['bankroll'])) {
            $user->setBankroll((float) $data['bankroll']);
        }

        if (isset($data['max_stake_percentage'])) {
            $user->setMaxStakePercentage((float) $data['max_stake_percentage']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'betting_profile' => $user->getBettingProfile(),
                'bankroll' => $user->getBankroll(),
                'bankroll_growth' => $user->getBankrollGrowth(),
            ],
        ]);
    }
}
