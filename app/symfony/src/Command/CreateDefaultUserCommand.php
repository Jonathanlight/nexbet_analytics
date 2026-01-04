<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-default-user',
    description: 'Créer l\'utilisateur par défaut pour l\'application',
)]
final class CreateDefaultUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création de l\'utilisateur par défaut');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@nexbet.com']);

        if ($existingUser) {
            $io->warning('L\'utilisateur admin@nexbet.com existe déjà.');

            if (!$io->confirm('Voulez-vous réinitialiser le mot de passe?', false)) {
                return Command::SUCCESS;
            }

            // Réinitialiser le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($existingUser, 'password');
            $existingUser->setPassword($hashedPassword);
            $this->entityManager->flush();

            $io->success('Mot de passe réinitialisé avec succès!');

            return Command::SUCCESS;
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail('admin@nexbet.com');
        $user->setName('Admin');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setBettingProfile('balanced');
        $user->setBankroll(1000.0);
        $user->setInitialBankroll(1000.0);
        $user->setMaxStakePercentage(5.0);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);

        // Sauvegarder en base de données
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Utilisateur créé avec succès!');

        $io->table(
            ['Propriété', 'Valeur'],
            [
                ['Email', 'admin@nexbet.com'],
                ['Mot de passe', 'password'],
                ['Nom', 'Admin'],
                ['Bankroll', '1000.0 €'],
                ['Profil de paris', 'balanced'],
            ]
        );

        return Command::SUCCESS;
    }
}
