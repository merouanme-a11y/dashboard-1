<?php

namespace App\Controller\Admin;

use App\Service\DirectoryServiceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
final class ServiceController extends AbstractController
{
    public function __construct(
        private DirectoryServiceManager $directoryServiceManager,
    ) {}

    #[Route('', name: 'admin_services', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $action = trim((string) $request->request->get('action', ''));

            if ($action === 'create_service') {
                $result = $this->directoryServiceManager->createService(
                    (string) $request->request->get('name', ''),
                    (string) $request->request->get('color', '')
                );

                $this->addFlash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));

                return $this->redirectToRoute('admin_services');
            }

            if ($action === 'update_service') {
                $result = $this->directoryServiceManager->updateService(
                    (int) $request->request->get('service_id', 0),
                    (string) $request->request->get('name', ''),
                    (string) $request->request->get('color', '')
                );

                $message = (string) ($result['message'] ?? '');
                if (($result['success'] ?? false) && (int) ($result['renamed_users'] ?? 0) > 0) {
                    $message .= ' ' . (int) $result['renamed_users'] . ' profil(s) mis a jour automatiquement.';
                }

                $this->addFlash(($result['success'] ?? false) ? 'success' : 'danger', trim($message));

                return $this->redirectToRoute('admin_services');
            }

            if ($action === 'delete_service') {
                $result = $this->directoryServiceManager->deleteService((int) $request->request->get('service_id', 0));

                $message = (string) ($result['message'] ?? '');
                if (($result['success'] ?? false) && (int) ($result['detached_users'] ?? 0) > 0) {
                    $message .= ' ' . (int) $result['detached_users'] . ' profil(s) ont ete detach(es) de ce service.';
                }

                $this->addFlash(($result['success'] ?? false) ? 'success' : 'danger', trim($message));

                return $this->redirectToRoute('admin_services');
            }
        }

        $services = $this->directoryServiceManager->getAdminRows();
        $assignedUsers = 0;
        foreach ($services as $service) {
            $assignedUsers += (int) ($service['usage_count'] ?? 0);
        }

        return $this->render('admin/services/index.html.twig', [
            'services' => $services,
            'serviceCount' => count($services),
            'assignedUsers' => $assignedUsers,
        ]);
    }
}
