<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un nouvel utilisateur pour tester.',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom', 'Admin')
            ->addArgument('prenom', InputArgument::OPTIONAL, 'Prenom', 'Super')
            ->addArgument('roles', InputArgument::OPTIONAL, 'Roles (JSON)', '["ROLE_ADMIN"]')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        
        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom($input->getArgument('nom'));
        $user->setPrenom($input->getArgument('prenom'));
        $user->setRoles(json_decode($input->getArgument('roles'), true) ?? ['ROLE_EMPLOYE']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setMotDePasse($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('L\'utilisateur %s a été créé avec succès.', $email));

        return Command::SUCCESS;
    }
}
