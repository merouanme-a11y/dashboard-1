<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Service\AdminUserDirectoryService;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
final class UsersController extends AbstractController
{
    public function __construct(
        private AdminUserDirectoryService $adminUserDirectoryService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'admin_utilisateurs', methods: ['GET'])]
    public function index(): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        $context = $this->adminUserDirectoryService->getPageContext();

        return $this->render('admin/users/index.html.twig', $context + [
            'userCount' => count((array) ($context['users'] ?? [])),
            'currentUserId' => ($this->getUser() instanceof Utilisateur) ? $this->getUser()->getId() : null,
        ]);
    }

    #[Route('/inline-update', name: 'admin_utilisateurs_inline_update', methods: ['POST'])]
    public function inlineUpdate(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(AdminUserDirectoryService::USERS_MODULE)) {
            return new JsonResponse(['ok' => false, 'message' => 'Acces refuse.'], 403);
        }

        if (!$this->isCsrfTokenValid('admin_users_inline_update', (string) $request->request->get('csrf'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        $actor = $this->getUser();
        if (!$actor instanceof Utilisateur) {
            return new JsonResponse(['ok' => false, 'message' => 'Acces refuse.'], 403);
        }

        $result = $this->adminUserDirectoryService->inlineUpdate(
            $actor,
            (int) $request->request->get('user_id', 0),
            (string) $request->request->get('field', ''),
            (string) $request->request->get('value', '')
        );

        return new JsonResponse($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    #[Route('/reset-password', name: 'admin_utilisateurs_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('admin_users_reset_password', (string) $request->request->get('csrf'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $result = $this->adminUserDirectoryService->resetPassword((int) $request->request->get('user_id', 0));
        $this->addFlash((string) ($result['flashType'] ?? (($result['success'] ?? false) ? 'success' : 'danger')), (string) ($result['message'] ?? ''));

        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/delete', name: 'admin_utilisateurs_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('admin_users_delete', (string) $request->request->get('csrf'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $actor = $this->getUser();
        if (!$actor instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $result = $this->adminUserDirectoryService->deleteUser(
            $actor,
            (int) $request->request->get('user_id', 0),
            (string) $request->request->get('current_password', '')
        );

        $this->addFlash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));

        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/import', name: 'admin_utilisateurs_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('admin_users_import', (string) $request->request->get('csrf'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $file = $request->files->get('import_file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $this->addFlash('danger', 'Fichier d import invalide.');

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $result = $this->adminUserDirectoryService->importUsersFromFile($file);
        $this->addFlash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));

        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/export/{format}', name: 'admin_utilisateurs_export', methods: ['GET'])]
    public function export(string $format, Request $request): Response
    {
        if (!$this->moduleService->isActive(AdminUserDirectoryService::USERS_MODULE)) {
            return new Response('Acces refuse.', 403);
        }

        if (!$this->isCsrfTokenValid('admin_users_export', (string) $request->query->get('csrf'))) {
            return new Response('Token CSRF invalide.', 403);
        }

        $format = strtolower(trim($format));
        $rows = $this->adminUserDirectoryService->getExportRows();
        $filename = 'utilisateurs-' . date('Ymd_His');

        if ($format === 'csv') {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['id', 'nom', 'prenom', 'email', 'service', 'departement', 'agence', 'telephone', 'numero_court', 'profile_type'], ';');
            foreach ($rows as $row) {
                fputcsv($stream, [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['nom'] ?? ''),
                    (string) ($row['prenom'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['service'] ?? ''),
                    (string) ($row['departement'] ?? ''),
                    (string) ($row['agence'] ?? ''),
                    (string) ($row['telephone'] ?? ''),
                    (string) ($row['numero_court'] ?? ''),
                    (string) ($row['profile_type'] ?? ''),
                ], ';');
            }
            rewind($stream);
            $content = stream_get_contents($stream) ?: '';
            fclose($stream);

            return new Response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            ]);
        }

        if ($format === 'xls') {
            $html = '<html><head><meta charset="UTF-8"></head><body><table border="1">';
            $html .= '<tr><th>id</th><th>nom</th><th>prenom</th><th>email</th><th>service</th><th>departement</th><th>agence</th><th>telephone</th><th>numero_court</th><th>profile_type</th></tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['nom'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['prenom'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['service'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['departement'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['agence'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['telephone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['numero_court'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($row['profile_type'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table></body></html>';

            return new Response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.xls"',
            ]);
        }

        return new Response('Format non supporte.', 400);
    }

    private function redirectIfModuleDisabled(): ?Response
    {
        if ($this->moduleService->isActive(AdminUserDirectoryService::USERS_MODULE)) {
            return null;
        }

        $this->addFlash('danger', 'Le module des utilisateurs est desactive.');

        return $this->redirectToRoute('admin_parametrage');
    }
}
