<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GanttLegacyRuntime
{
    private bool $booted = false;

    public function __construct(
        private KernelInterface $kernel,
        private UrlGeneratorInterface $urlGenerator,
        private string $youtrackUrl = 'https://maintenance.adep.com',
        private string $youtrackToken = '',
        private string $youtrackProject = 'MTN',
        private string $youtrackDefaultProjectLeaderId = '1-1',
        private string $youtrackDefaultProjectLeaderLogin = 'Merouan',
        private string $youtrackDefaultProjectLeaderEmail = 'm.hamzaoui@adep.com',
        private string $youtrackProjectTemplateShortName = 'MLDP',
    ) {}

    public function bootForUser(?Utilisateur $user): void
    {
        $this->boot();

        if ($user instanceof Utilisateur) {
            app_set_current_user($this->createFrontendUserPayload($user));
            return;
        }

        app_set_current_user(null);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $projectDir = $this->kernel->getProjectDir();
        $storageDir = $projectDir . '/var/gantt';
        $exportDir = $projectDir . '/public/modules/gantt-projects/export';

        if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new \RuntimeException('Impossible de creer le dossier de stockage Gantt.');
        }

        if (!is_dir($exportDir) && !@mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
            throw new \RuntimeException('Impossible de creer le dossier d export Gantt.');
        }

        $this->defineConstant('APP_GANTT_STORAGE_DIR', $storageDir);
        $this->defineConstant('APP_GANTT_EXPORT_DIR', $exportDir);
        $this->defineConstant('YT_BASE_URL', rtrim($this->youtrackUrl, '/'));
        $this->defineConstant('YT_TOKEN', $this->youtrackToken);
        $this->defineConstant('YT_PROJECT', $this->youtrackProject);
        $this->defineConstant('YT_DEFAULT_PROJECT_LEADER_ID', $this->youtrackDefaultProjectLeaderId);
        $this->defineConstant('YT_DEFAULT_PROJECT_LEADER_LOGIN', $this->youtrackDefaultProjectLeaderLogin);
        $this->defineConstant('YT_DEFAULT_PROJECT_LEADER_EMAIL', $this->youtrackDefaultProjectLeaderEmail);
        $this->defineConstant('YT_PROJECT_TEMPLATE_SHORT_NAME', $this->youtrackProjectTemplateShortName);
        $this->defineConstant('YT_FIELD_TYPE', 'Type');
        $this->defineConstant('YT_FIELD_PRIORITY', 'Priority');
        $this->defineConstant('YT_FIELD_SERVICE', 'Service');
        $this->defineConstant('YT_FIELD_ASSIGNEE', 'Assignee');
        $this->defineConstant('YT_FIELD_DUE_DATE', 'Date échéance');

        require_once $projectDir . '/src/Legacy/Gantt/runtime.php';
        require_once $projectDir . '/src/Legacy/Gantt/cache.php';
        require_once $projectDir . '/src/Legacy/Gantt/database.php';
        require_once $projectDir . '/src/Legacy/Gantt/projects_repository.php';
        require_once $projectDir . '/src/Legacy/Gantt/projects_import.php';
        require_once $projectDir . '/src/Legacy/Gantt/youtrack.php';

        app_set_gantt_export_download_url_template(
            $this->urlGenerator->generate(
                'app_gantt_projects_export_download',
                ['fileName' => '__FILE__']
            )
        );

        $this->booted = true;
    }

    private function defineConstant(string $name, string $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    public function createFrontendUserPayload(Utilisateur $user): array
    {
        $displayName = trim((string) ($user->getPrenom() ?? '') . ' ' . (string) ($user->getNom() ?? ''));
        $email = trim((string) ($user->getEmail() ?? ''));
        $roles = $user->getRoles();

        return [
            'id' => (string) ($user->getId() ?? ''),
            'username' => $email !== '' ? $email : (string) ($user->getId() ?? ''),
            'displayName' => $displayName !== '' ? $displayName : ($email !== '' ? $email : 'Utilisateur'),
            'email' => $email,
            'prenom' => (string) ($user->getPrenom() ?? ''),
            'nom' => (string) ($user->getNom() ?? ''),
            'service' => trim((string) ($user->getService() ?? '')),
            'darkmode' => $user->isDarkmode(),
            'isAdmin' => in_array('ROLE_ADMIN', $roles, true),
            'profileType' => $user->getEffectiveProfileType(),
            'role' => in_array('ROLE_ADMIN', $roles, true) ? 'Admin' : $user->getEffectiveProfileType(),
        ];
    }
}
