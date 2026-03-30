<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\DirectoryServiceManager;
use App\Service\FileUploadService;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/annuaire')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AnnuaireController extends AbstractController
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private DirectoryServiceManager $directoryServiceManager,
        private FileUploadService $fileUploadService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_annuaire')]
    public function index(): Response
    {
        $this->ensureModuleIsActive('annuaire');

        $utilisateurs = $this->utilisateurRepository->findDirectoryRows();
        $serviceColors = $this->directoryServiceManager->getServiceColorMap();
        $contacts = array_map(function (array $user) use ($serviceColors): array {
            $prenom = trim((string) ($user['prenom'] ?? ''));
            $nom = trim((string) ($user['nom'] ?? ''));
            $service = trim((string) ($user['service'] ?? ''));
            $photo = trim((string) ($user['photo'] ?? ''));
            $telephone = trim((string) ($user['telephone'] ?? ''));
            $numeroCourt = trim((string) ($user['numeroCourt'] ?? ''));
            $email = trim((string) ($user['email'] ?? ''));
            $departement = trim((string) ($user['departement'] ?? ''));
            $agence = trim((string) ($user['agence'] ?? ''));
            $displayName = trim($prenom . ' ' . $nom);
            $initials = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
            $searchBlob = mb_strtolower(trim(implode(' ', [
                $nom,
                $prenom,
                $email,
                $service,
                $departement,
                $agence,
                $telephone,
                $numeroCourt,
            ])));
            $serviceStyle = $service !== '' && isset($serviceColors[$service])
                ? 'background:' . $serviceColors[$service] . '; color:#fff; border:1px solid ' . $serviceColors[$service] . ';'
                : 'background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border);';
            $photoUrl = $this->resolvePhotoUrl($photo);
            $photoIsExternal = $photoUrl !== '' && preg_match('#^https?://#i', $photoUrl) === 1;

            return [
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'service' => $service,
                'departement' => $departement,
                'agence' => $agence,
                'telephone' => $telephone,
                'numeroCourt' => $numeroCourt,
                'photoUrl' => $photoUrl,
                'photoIsExternal' => $photoIsExternal,
                'displayName' => $displayName !== '' ? $displayName : '-',
                'displayNameRaw' => $displayName,
                'initials' => $initials !== '' ? $initials : '--',
                'searchBlob' => $searchBlob,
                'serviceStyle' => $serviceStyle,
                'serviceLabel' => $service !== '' ? $service : '-',
                'departementLabel' => $departement !== '' ? $departement : '-',
                'agenceLabel' => $agence !== '' ? $agence : '-',
                'telephoneLabel' => $telephone !== '' ? $telephone : '-',
                'numeroCourtLabel' => $numeroCourt !== '' ? $numeroCourt : '-',
                'callLink' => $this->buildCallLink($telephone !== '' ? $telephone : $numeroCourt),
                'shortCallLink' => $this->buildCallLink($numeroCourt),
                'teamsLink' => $this->buildTeamsLink($email),
            ];
        }, $utilisateurs);

        return $this->render('annuaire/index.html.twig', [
            'contacts' => $contacts,
            'totalCount' => count($contacts),
        ]);
    }

    private function ensureModuleIsActive(string $moduleName): void
    {
        if (!$this->moduleService->isActive($moduleName)) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }

    private function resolvePhotoUrl(string $photo): string
    {
        $photo = trim(str_replace('\\', '/', $photo));
        if ($photo === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $photo)) {
            return $photo;
        }

        return $this->fileUploadService->resolvePublicPath($photo);
    }

    private function buildCallLink(string $value): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($value)) ?? '';

        return $normalized !== '' ? 'callto:' . $normalized : '';
    }

    private function buildTeamsLink(string $email): string
    {
        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'msteams:/l/chat/0/0?users=' . rawurlencode($email) : '';
    }
}
