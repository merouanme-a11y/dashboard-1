<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\DirectoryServiceManager;
use App\Service\FileUploadService;
use App\Service\LegacyDirectoryOptionsService;
use App\Service\ModuleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private DirectoryServiceManager $directoryServiceManager,
        private FileUploadService $fileUploadService,
        private LegacyDirectoryOptionsService $legacyDirectoryOptionsService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function show(Request $request): Response
    {
        $this->ensureModuleIsActive('profile');

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        if ($request->getMethod() === 'POST') {
            return $this->handleProfileUpdate($request, $user);
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'isCreateMode' => false,
        ] + $this->buildProfileFormContext($user));
    }

    #[Route('/{id}', name: 'app_profile_view', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): Response
    {
        $this->ensureModuleIsActive('profile');

        $user = $this->utilisateurRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouve');
        }

        if ($request->getMethod() === 'POST') {
            return $this->handleProfileUpdate($request, $user);
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'isCreateMode' => false,
        ] + $this->buildProfileFormContext($user));
    }

    #[Route('/create', name: 'app_profile_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): Response
    {
        $this->ensureModuleIsActive('profile');

        if ($request->getMethod() === 'POST') {
            $errors = $this->validateProfileCreation($request);

            if (empty($errors)) {
                $data = $request->request->all();
                [$service, $departement, $agence] = $this->extractDirectorySelectionsFromRequest($request);
                $newUser = new Utilisateur();
                $newUser->setNom(trim($data['nom'] ?? ''));
                $newUser->setPrenom(trim($data['prenom'] ?? ''));
                $newUser->setEmail(trim($data['email'] ?? ''));
                $newUser->setService($service);
                $newUser->setDepartement($departement);
                $newUser->setAgence($agence);
                $newUser->setTelephone(trim($data['telephone'] ?? '') ?: null);
                $newUser->setNumeroCourt(trim($data['numero_court'] ?? '') ?: null);
                $newUser->setCodePostal(trim($data['code_postal'] ?? '') ?: null);
                $newUser->setProfileType($data['profile_type'] ?? 'Employe');
                $newUser->setForcePasswordChange(true);

                $password = trim($data['initial_password'] ?? '');
                $hashedPassword = $this->passwordHasher->hashPassword($newUser, $password);
                $newUser->setMotDePasse($hashedPassword);

                $this->em->persist($newUser);
                $this->em->flush();

                $this->addFlash('success', 'Utilisateur cree avec succes');
                return $this->redirectToRoute('app_profile_view', ['id' => $newUser->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('profile/create.html.twig', [
            'isCreateMode' => true,
            'formData' => $request->request->all(),
        ] + $this->buildProfileFormContext(formData: $request->request->all()));
    }

    private function handleProfileUpdate(Request $request, Utilisateur $user): Response
    {
        $action = $request->request->get('action');

        switch ($action) {
            case 'update_profile':
                return $this->updateProfile($request, $user);
            case 'update_password':
                return $this->updatePassword($request, $user);
            case 'update_photo':
                return $this->updatePhoto($request, $user);
            case 'delete_photo':
                return $this->deletePhoto($user);
        }

        return $this->redirectToProfileRoute($user);
    }

    private function updateProfile(Request $request, Utilisateur $user): Response
    {
        $errors = $this->validateProfileUpdate($request, $user);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToProfileRoute($user);
        }

        $user->setNom(trim($request->request->get('nom', $user->getNom())));
        $user->setPrenom(trim($request->request->get('prenom', $user->getPrenom())));
        $user->setEmail(trim($request->request->get('email', $user->getEmail())));
        [$service, $departement, $agence] = $this->extractDirectorySelectionsFromRequest($request);
        $user->setService($service);
        $user->setDepartement($departement);
        $user->setAgence($agence);
        $telephone = trim($request->request->get('telephone', ''));
        $user->setTelephone($telephone ?: null);
        $numeroCourt = trim($request->request->get('numero_court', ''));
        $user->setNumeroCourt($numeroCourt ?: null);
        $codePostal = trim($request->request->get('code_postal', ''));
        $user->setCodePostal($codePostal ?: null);

        if ($this->isGranted('ROLE_ADMIN')) {
            $user->setProfileType($request->request->get('profile_type', $user->getProfileType()));
        }

        $photoFile = $request->files->get('photo');
        if ($photoFile instanceof UploadedFile) {
            try {
                $this->replaceUserPhoto($photoFile, $user);
            } catch (\RuntimeException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToProfileRoute($user);
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Profil mis a jour avec succes');

        return $this->redirectToProfileRoute($user);
    }

    private function updatePassword(Request $request, Utilisateur $user): Response
    {
        $currentPassword = $request->request->get('current_password', '');
        $newPassword = $request->request->get('new_password', '');
        $confirmPassword = $request->request->get('confirm_password', '');

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect');
            return $this->redirectToProfileRoute($user);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
            return $this->redirectToProfileRoute($user);
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caracteres');
            return $this->redirectToProfileRoute($user);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setMotDePasse($hashedPassword);
        $user->setForcePasswordChange(false);

        $this->em->flush();
        $this->addFlash('success', 'Mot de passe mis a jour avec succes');

        return $this->redirectToProfileRoute($user);
    }

    private function updatePhoto(Request $request, Utilisateur $user): Response
    {
        $photoFile = $request->files->get('photo');
        if (!$photoFile instanceof UploadedFile) {
            $this->addFlash('error', 'Veuillez selectionner une photo JPG ou PNG.');

            return $this->redirectToProfileRoute($user);
        }

        try {
            $this->replaceUserPhoto($photoFile, $user);
            $this->em->flush();
            $this->addFlash('success', 'Photo mise a jour avec succes');
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToProfileRoute($user);
    }

    private function deletePhoto(Utilisateur $user): Response
    {
        if ($user->getPhoto()) {
            $this->fileUploadService->deleteFile($user->getPhoto());
            $user->setPhoto(null);
            $this->em->flush();
            $this->addFlash('success', 'Photo supprimee');
        }

        return $this->redirectToProfileRoute($user);
    }

    private function validateProfileCreation(Request $request): array
    {
        $errors = [];
        $data = $request->request->all();

        $prenom = trim($data['prenom'] ?? '');
        $nom = trim($data['nom'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = trim($data['initial_password'] ?? '');
        $confirmPassword = trim($data['confirm_initial_password'] ?? '');

        if ($prenom === '') {
            $errors[] = 'Le prenom est obligatoire.';
        }

        if ($nom === '') {
            $errors[] = 'Le nom est obligatoire.';
        }

        if ($email === '') {
            $errors[] = 'L\'email est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email est invalide.';
        } elseif ($this->utilisateurRepository->findOneBy(['email' => $email])) {
            $errors[] = 'Cette adresse email existe deja.';
        }

        if ($password === '') {
            $errors[] = 'Le mot de passe initial est obligatoire.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caracteres.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'La confirmation du mot de passe ne correspond pas.';
        }

        return array_merge($errors, $this->validateDirectorySelections($request));
    }

    private function validateProfileUpdate(Request $request, Utilisateur $user): array
    {
        $errors = [];
        $data = $request->request->all();

        $email = trim($data['email'] ?? '');

        if ($email === '') {
            $errors[] = 'L\'email est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email est invalide.';
        } elseif ($email !== $user->getEmail()) {
            $existingUser = $this->utilisateurRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                $errors[] = 'Cette adresse email existe deja.';
            }
        }

        return array_merge($errors, $this->validateDirectorySelections($request, $user));
    }

    private function ensureModuleIsActive(string $moduleName): void
    {
        if (!$this->moduleService->isActive($moduleName)) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }

    private function replaceUserPhoto(UploadedFile $photoFile, Utilisateur $user): void
    {
        $previousPhoto = (string) ($user->getPhoto() ?? '');
        $photoPath = $this->fileUploadService->uploadProfilePhoto($photoFile, $user->getId());
        $user->setPhoto($photoPath);

        if ($previousPhoto !== '' && $previousPhoto !== $photoPath) {
            $this->fileUploadService->deleteFile($previousPhoto);
        }
    }

    private function redirectToProfileRoute(Utilisateur $user): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $user->getId()) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->redirectToRoute('app_profile_view', ['id' => $user->getId()]);
    }

    private function validateDirectorySelections(Request $request, ?Utilisateur $user = null): array
    {
        $errors = [];
        $service = trim((string) $request->request->get('service', ''));
        $agence = trim((string) $request->request->get('agence', ''));
        $departement = trim((string) $request->request->get('departement', ''));
        $currentService = trim((string) ($user?->getService() ?? ''));
        $currentAgence = trim((string) ($user?->getAgence() ?? ''));
        $currentDepartement = trim((string) ($user?->getDepartement() ?? ''));

        if ($service !== '' && !$this->directoryServiceManager->serviceExists($service) && $service !== $currentService) {
            $errors[] = 'Le service selectionne est invalide.';
        }

        if ($agence !== '') {
            $resolvedDepartement = $this->legacyDirectoryOptionsService->resolveDepartementForAgence($agence);
            if ($resolvedDepartement === null && $agence !== $currentAgence) {
                $errors[] = 'L\'agence selectionnee est invalide.';
            }
        } elseif ($departement !== '' && mb_strlen($departement) > 255) {
            $errors[] = 'Le departement selectionne est invalide.';
        }

        return $errors;
    }

    private function extractDirectorySelectionsFromRequest(Request $request): array
    {
        $service = trim((string) $request->request->get('service', ''));
        $agence = trim((string) $request->request->get('agence', ''));
        $departement = trim((string) $request->request->get('departement', ''));

        if ($agence !== '') {
            $resolvedDepartement = $this->legacyDirectoryOptionsService->resolveDepartementForAgence($agence);
            if ($resolvedDepartement !== null) {
                $departement = $resolvedDepartement;
            }
        }

        return [
            $service !== '' ? $service : null,
            $departement !== '' ? $departement : null,
            $agence !== '' ? $agence : null,
        ];
    }

    private function buildProfileFormContext(?Utilisateur $user = null, array $formData = []): array
    {
        $selectedService = trim((string) ($formData['service'] ?? ($user?->getService() ?? '')));
        $selectedDepartement = trim((string) ($formData['departement'] ?? ($user?->getDepartement() ?? '')));
        $selectedAgence = trim((string) ($formData['agence'] ?? ($user?->getAgence() ?? '')));

        $serviceOptions = $this->appendChoice(
            $this->directoryServiceManager->getServiceOptions(),
            $selectedService
        );

        $departementOptions = $this->appendChoice(
            $this->legacyDirectoryOptionsService->getDepartementOptions(),
            $selectedDepartement
        );

        $agenceOptions = $this->appendAgenceChoice(
            $this->legacyDirectoryOptionsService->getAgenceOptions(),
            $selectedAgence,
            $selectedDepartement
        );

        return [
            'serviceOptions' => $serviceOptions,
            'departementOptions' => $departementOptions,
            'agenceOptions' => $agenceOptions,
        ];
    }

    private function appendChoice(array $choices, string $value): array
    {
        if ($value !== '' && !in_array($value, $choices, true)) {
            $choices[] = $value;
            sort($choices, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $choices;
    }

    private function appendAgenceChoice(array $choices, string $agence, string $departement): array
    {
        if ($agence === '') {
            return $choices;
        }

        foreach ($choices as $choice) {
            if (($choice['agence'] ?? '') === $agence) {
                return $choices;
            }
        }

        $choices[] = [
            'agence' => $agence,
            'departement' => $departement,
        ];

        usort($choices, static function (array $left, array $right): int {
            $leftDept = (string) ($left['departement'] ?? '');
            $rightDept = (string) ($right['departement'] ?? '');
            $leftAgence = (string) ($left['agence'] ?? '');
            $rightAgence = (string) ($right['agence'] ?? '');

            return strnatcasecmp($leftDept, $rightDept) ?: strnatcasecmp($leftAgence, $rightAgence);
        });

        return $choices;
    }
}
