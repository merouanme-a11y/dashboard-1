<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\LivreDeCaisseLegacyRuntime;
use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;
use ZipArchive;

#[Route('/livre-de-caisse')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class LivreDeCaisseController extends AbstractController
{
    public function __construct(
        private LivreDeCaisseLegacyRuntime $livreDeCaisseLegacyRuntime,
        private Packages $packages,
    ) {}

    #[Route('', name: 'app_livre_de_caisse', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_livre_de_caisse'])]
    public function index(Request $request): Response
    {
        $user = $this->getRequiredUser();
        $pdo = $this->livreDeCaisseLegacyRuntime->bootForUser($user);
        $businessDate = ldcResolveBusinessDate((string) $request->query->get('date', ''));
        $pageBaseUrl = $this->generateUrl('app_livre_de_caisse');
        $listingPageBaseUrl = $this->generateUrl('app_livre_de_caisse_listing');
        $pageUrlBuilder = $this->createDateAwareUrlBuilder($pageBaseUrl, $businessDate);
        $pageUrl = $pageUrlBuilder();

        if ($request->query->has('attachment')) {
            $attachmentId = (int) $request->query->get('attachment');
            $attachment = ldcFetchAttachmentById($pdo, $attachmentId);

            if ($attachment === null) {
                throw $this->createNotFoundException('Piece jointe introuvable.');
            }

            return $this->createAttachmentResponse($attachment, ((string) $request->query->get('download', '0')) === '1');
        }

        if ($request->isMethod('POST')) {
            return $this->handleMainPost($request, $pdo, $businessDate, $pageUrl, $pageUrlBuilder, $listingPageBaseUrl, $user);
        }

        $flash = $this->popLegacyFlash($request->getSession());
        $dailyState = ldcFetchDailyState($pdo, $businessDate);
        $entries = ldcFetchEntries($pdo, $businessDate);
        $totaux = ldcGetTotaux($entries);
        $fondDebut = ldcGetFondCaisseDebutJournee($pdo, $businessDate);
        $fondFin = ldcGetFondCaisseFinJournee($pdo, $businessDate);
        $isDayClosed = ldcIsDailyClosed($dailyState);
        $currentBordereauNum = ldcGetBordereauNumber($pdo, $businessDate);
        $nextChrono = ldcGetNextChrono($pdo);
        $numRemiseEspeces = trim((string) ($dailyState['num_remise_especes'] ?? ''));
        $numRemiseCheque = trim((string) ($dailyState['num_remise_cheque'] ?? ''));
        $editId = (int) $request->query->get('edit', 0);
        $editEntry = $editId > 0 ? ldcFetchEntryById($pdo, $editId, $businessDate) : null;
        $isEditing = !$isDayClosed && $editEntry !== null;
        $editAttachments = $isEditing ? ldcFetchAttachments($pdo, (int) $editEntry['id']) : [];
        $showSaisieForm = !$isDayClosed && ($isEditing || $request->query->has('open'));
        $promptFondCaisseConfirmation = $entries === [];
        $closedAtLabel = $isDayClosed ? ldcFormatDisplayDateTime((string) ($dailyState['journee_cloturee_at'] ?? '')) : '';

        $currentTypeAffaire = $editEntry['type_affaire'] ?? '';
        $currentTypeAffaireHelper = ldcGetTypeAffaireHelper($currentTypeAffaire);
        $currentTypeEncaissementOptions = ldcGetTypeEncaissementOptionsForTypeAffaire($currentTypeAffaire !== '' ? $currentTypeAffaire : null);
        $currentAnticipationValues = ldcNormalizeAnticipationMonthValues($editEntry['mois_anticipation'] ?? '');
        $currentAnticipationOptions = ldcGetAnticipationMonthOptions(
            $editEntry['date_reglement'] ?? $businessDate,
            LDC_ANTICIPATION_MONTHS_WINDOW,
            $currentAnticipationValues
        );

        $showField = static fn (string $field): bool => ldcTypeAffaireShowsField($currentTypeAffaire, $field);
        $showSection = static fn (array $fields): bool => ldcTypeAffaireShowsAnyField($currentTypeAffaire, $fields);
        $requireField = static fn (string $field): bool => ldcTypeAffaireRequiresField($currentTypeAffaire, $field);
        $formatTableValue = static fn (array $entry, string $field): string => ldcFormatEntryValueForTable($entry, $field);

        $extendedTableColumns = [
            ['key' => 'num_cheque', 'label' => 'N° Chèque / Transaction'],
            ['key' => 'date_reglement', 'label' => 'Date règlement'],
            ['key' => 'mois_anticipation', 'label' => 'Mois anticipation'],
            ['key' => 'reglement_avis', 'label' => 'Règlement avis échéance'],
            ['key' => 'avenant', 'label' => 'Avenant'],
            ['key' => 'regul_impaye', 'label' => 'Régul impayés'],
            ['key' => 'regul_mise_demeure', 'label' => 'Régul mise en demeure'],
            ['key' => 'num_adhesion', 'label' => 'N° Adhésion'],
            ['key' => 'date_effet', 'label' => 'Date effet'],
            ['key' => 'formule_produit', 'label' => 'Formule / Produit'],
            ['key' => 'mandataire', 'label' => 'Mandataire'],
            ['key' => 'dsu', 'label' => 'DSU'],
            ['key' => 'comptant_prelever', 'label' => 'Comptant à prélever'],
            ['key' => 'comptant_offert', 'label' => 'Comptant offert'],
            ['key' => 'appel_cotisation', 'label' => 'Appel de cotisation'],
        ];

        $caisseCards = [
            ['title' => 'Total Espèces', 'amount' => $totaux['especes'], 'count' => $totaux['nb_especes'], 'class' => 'is-especes'],
            ['title' => 'Total Chèque', 'amount' => $totaux['cheques'], 'count' => $totaux['nb_cheques'], 'class' => 'is-cheque'],
            ['title' => 'Total CB', 'amount' => $totaux['cb'], 'count' => $totaux['nb_cb'], 'class' => 'is-cb'],
            ['title' => 'Comptant à prélever', 'amount' => $totaux['comptant_prelever'], 'count' => $totaux['nb_comptant_prelever'], 'class' => 'is-prelever'],
            ['title' => 'Comptant offert', 'amount' => $totaux['comptant_offert'], 'count' => $totaux['nb_comptant_offert'], 'class' => 'is-offert'],
            ['title' => 'Appel de cotisation', 'amount' => $totaux['appel_cotisation'], 'count' => $totaux['nb_appel_cotisation'], 'class' => 'is-appel'],
        ];

        $caisseRows = [
            ['label' => 'Espèces', 'count' => $totaux['nb_especes'], 'amount' => $totaux['especes'], 'remise_field' => 'num_remise_especes', 'remise_value' => $numRemiseEspeces, 'remise_placeholder' => 'N° remise Espèces'],
            ['label' => 'Chèques', 'count' => $totaux['nb_cheques'], 'amount' => $totaux['cheques'], 'remise_field' => 'num_remise_cheque', 'remise_value' => $numRemiseCheque, 'remise_placeholder' => 'N° remise Chèque'],
            ['label' => 'CB', 'count' => $totaux['nb_cb'], 'amount' => $totaux['cb'], 'remise_field' => null, 'remise_value' => '', 'remise_placeholder' => ''],
            ['label' => 'Comptant à prélever', 'count' => $totaux['nb_comptant_prelever'], 'amount' => $totaux['comptant_prelever'], 'remise_field' => null, 'remise_value' => '', 'remise_placeholder' => ''],
            ['label' => 'Comptant offert', 'count' => $totaux['nb_comptant_offert'], 'amount' => $totaux['comptant_offert'], 'remise_field' => null, 'remise_value' => '', 'remise_placeholder' => ''],
            ['label' => 'Appel de cotisation', 'count' => $totaux['nb_appel_cotisation'], 'amount' => $totaux['appel_cotisation'], 'remise_field' => null, 'remise_value' => '', 'remise_placeholder' => ''],
        ];

        $attachmentAcceptedFormats = implode(', ', array_values(ldcGetAllowedAttachmentMimeMap()));
        $attachmentMaxSizeLabel = ldcFormatAttachmentSize(LDC_ATTACHMENT_MAX_FILE_SIZE);

        $flashClassMap = [
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
        ];

        $content = $this->renderPhpTemplate($this->getParameter('kernel.project_dir') . '/templates/livre_de_caisse/partials/main.php', [
            'flash' => $flash,
            'flashClassMap' => $flashClassMap,
            'isDayClosed' => $isDayClosed,
            'closedAtLabel' => $closedAtLabel,
            'entries' => $entries,
            'totaux' => $totaux,
            'fondDebut' => $fondDebut,
            'fondFin' => $fondFin,
            'currentBordereauNum' => $currentBordereauNum,
            'nextChrono' => $nextChrono,
            'pageUrl' => $pageUrl,
            'buildPageUrl' => $pageUrlBuilder,
            'businessDate' => $businessDate,
            'agenceContext' => ldcGetAgenceContext($this->livreDeCaisseLegacyRuntime->createFrontendUserPayload($user)),
            'listingPageBaseUrl' => $listingPageBaseUrl,
            'showSaisieForm' => $showSaisieForm,
            'isEditing' => $isEditing,
            'editEntry' => $editEntry,
            'editAttachments' => $editAttachments,
            'promptFondCaisseConfirmation' => $promptFondCaisseConfirmation,
            'currentTypeAffaire' => $currentTypeAffaire,
            'currentTypeAffaireHelper' => $currentTypeAffaireHelper,
            'currentTypeEncaissementOptions' => $currentTypeEncaissementOptions,
            'currentAnticipationValues' => $currentAnticipationValues,
            'currentAnticipationOptions' => $currentAnticipationOptions,
            'showField' => $showField,
            'showSection' => $showSection,
            'requireField' => $requireField,
            'formatTableValue' => $formatTableValue,
            'extendedTableColumns' => $extendedTableColumns,
            'caisseCards' => $caisseCards,
            'caisseRows' => $caisseRows,
            'dailyState' => $dailyState,
            'numRemiseEspeces' => $numRemiseEspeces,
            'numRemiseCheque' => $numRemiseCheque,
            'attachmentAcceptedFormats' => $attachmentAcceptedFormats,
            'attachmentMaxSizeLabel' => $attachmentMaxSizeLabel,
            'cssUrl' => $this->packages->getUrl('modules/livre-de-caisse/style.css'),
            'jsUrl' => $this->packages->getUrl('modules/livre-de-caisse/app.js'),
            'cssVersion' => $this->getAssetVersion('public/modules/livre-de-caisse/style.css'),
            'jsVersion' => $this->getAssetVersion('public/modules/livre-de-caisse/app.js'),
        ]);

        return $this->render('livre_de_caisse/page.html.twig', [
            'pageTitle' => 'Livre de caisse',
            'content' => $content,
        ]);
    }

    #[Route('/listing', name: 'app_livre_de_caisse_listing', methods: ['GET'], defaults: ['_managed_page_path' => 'app_livre_de_caisse_listing'])]
    public function listing(Request $request): Response
    {
        $user = $this->getRequiredUser();
        $pdo = $this->livreDeCaisseLegacyRuntime->bootForUser($user);
        $agenceContext = ldcGetAgenceContext($this->livreDeCaisseLegacyRuntime->createFrontendUserPayload($user));
        $pageBaseUrl = $this->generateUrl('app_livre_de_caisse_listing');
        $ldcPageBaseUrl = $this->generateUrl('app_livre_de_caisse');
        $buildPageUrl = static function (array $params = []) use ($pageBaseUrl): string {
            $query = array_filter(
                $params,
                static fn ($value): bool => $value !== null && $value !== ''
            );

            return $query === [] ? $pageBaseUrl : ($pageBaseUrl . '?' . http_build_query($query));
        };

        if ((string) $request->query->get('download_attachments', '0') === '1') {
            $downloadDate = ldcResolveBusinessDate((string) $request->query->get('date', ''));
            $attachments = ldcFetchAttachmentsByBusinessDate($pdo, $downloadDate);
            $archiveFileName = sprintf(
                'LDC_%s_%s_%s_pieces-jointes',
                (string) ($agenceContext['departement'] !== '' ? $agenceContext['departement'] : 'NA'),
                (string) ($agenceContext['agence'] !== '' ? $agenceContext['agence'] : 'Agence'),
                $downloadDate
            );

            return $this->createAttachmentsArchiveResponse($attachments, $archiveFileName);
        }

        $flash = $this->popLegacyFlash($request->getSession());
        $highlightDate = $request->query->has('highlight_date')
            ? ldcResolveBusinessDate((string) $request->query->get('highlight_date'))
            : '';
        $historyBooks = ldcFetchDailyBooks($pdo);
        $totalEntries = array_reduce(
            $historyBooks,
            static fn (int $carry, array $book): int => $carry + (int) ($book['entry_count'] ?? 0),
            0
        );
        $flashClassMap = [
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
        ];

        $content = $this->renderPhpTemplate($this->getParameter('kernel.project_dir') . '/templates/livre_de_caisse/partials/listing.php', [
            'flash' => $flash,
            'flashClassMap' => $flashClassMap,
            'agenceContext' => $agenceContext,
            'historyBooks' => $historyBooks,
            'totalEntries' => $totalEntries,
            'highlightDate' => $highlightDate,
            'ldcPageBaseUrl' => $ldcPageBaseUrl,
            'buildPageUrl' => $buildPageUrl,
            'cssUrl' => $this->packages->getUrl('modules/livre-de-caisse/style.css'),
            'cssVersion' => $this->getAssetVersion('public/modules/livre-de-caisse/style.css'),
        ]);

        return $this->render('livre_de_caisse/page.html.twig', [
            'pageTitle' => 'Listing livre de caisse agence',
            'content' => $content,
        ]);
    }

    private function handleMainPost(
        Request $request,
        PDO $pdo,
        string $businessDate,
        string $pageUrl,
        callable $buildPageUrl,
        string $listingPageBaseUrl,
        Utilisateur $user,
    ): RedirectResponse {
        $action = trim((string) $request->request->get('ldc_action', ''));
        $redirectUrl = $pageUrl;
        $session = $request->getSession();
        $currentUserId = (string) ($user->getId() ?? '');

        if ($action === '' && $this->isPostPayloadTooLarge($request)) {
            $this->pushLegacyFlash(
                $session,
                'danger',
                sprintf(
                    'Les pieces jointes sont trop volumineuses pour le serveur. Limites actuelles : %s par fichier, %s pour la requete complete.',
                    ini_get('upload_max_filesize') ?: '2M',
                    ini_get('post_max_size') ?: '8M'
                )
            );

            return $this->redirect($redirectUrl);
        }

        try {
            switch ($action) {
                case 'nouveau':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    if ((string) $request->request->get('fond_caisse_confirme', '0') === '1') {
                        ldcUpsertDailyState(
                            $pdo,
                            ldcNormalizeMoneyInput($request->request->get('fond_caisse_debut_journee', ldcGetFondCaisseDebutJournee($pdo, $businessDate))),
                            $businessDate,
                            $currentUserId
                        );
                    }

                    $entry = ldcBuildEntryFromPost($request->request->all(), null, ldcGetNextChrono($pdo), $businessDate);
                    $entryId = ldcPersistEntry($pdo, $entry, $currentUserId);
                    ldcPersistUploadedAttachments($pdo, $entryId, $businessDate, $_FILES['pieces_jointes'] ?? null, $currentUserId);

                    $this->pushLegacyFlash($session, 'success', 'Règlement enregistré - Chrono #' . $entry['chrono']);
                    break;

                case 'modifier':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    $entryId = (int) $request->request->get('edit_id', 0);
                    $existingEntry = $entryId > 0 ? ldcFetchEntryById($pdo, $entryId, $businessDate) : null;

                    if ($existingEntry === null) {
                        $this->pushLegacyFlash($session, 'danger', 'Le règlement à modifier est introuvable.');
                        break;
                    }

                    $entry = ldcBuildEntryFromPost($request->request->all(), $existingEntry, (int) $existingEntry['chrono'], $businessDate);
                    ldcPersistEntry($pdo, $entry, $currentUserId, $entryId);
                    ldcPersistUploadedAttachments($pdo, $entryId, $businessDate, $_FILES['pieces_jointes'] ?? null, $currentUserId);

                    $this->pushLegacyFlash($session, 'success', 'Règlement #' . $entry['chrono'] . ' modifié.');
                    break;

                case 'supprimer':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    $entryId = (int) $request->request->get('delete_id', 0);
                    $deletedEntry = $entryId > 0 ? ldcDeleteEntry($pdo, $entryId, $businessDate) : null;

                    if ($deletedEntry === null) {
                        $this->pushLegacyFlash($session, 'danger', 'Le règlement à supprimer est introuvable.');
                        break;
                    }

                    $this->pushLegacyFlash($session, 'warning', 'Règlement Chrono #' . $deletedEntry['chrono'] . ' supprimé.');
                    break;

                case 'supprimer_piece_jointe':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    $attachmentId = (int) $request->request->get('attachment_id', 0);
                    $deletedAttachment = $attachmentId > 0 ? ldcDeleteAttachment($pdo, $attachmentId) : null;

                    if ($deletedAttachment === null) {
                        $this->pushLegacyFlash($session, 'danger', 'La pièce jointe à supprimer est introuvable.');
                        break;
                    }

                    $parentEntryId = (int) ($deletedAttachment['attachment_entry_id'] ?? 0);
                    if ($parentEntryId > 0) {
                        $redirectUrl = $buildPageUrl(['edit' => $parentEntryId]);
                    }

                    $this->pushLegacyFlash($session, 'warning', 'Pièce jointe supprimée.');
                    break;

                case 'update_fond_caisse':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    $newFond = ldcNormalizeMoneyInput($request->request->get('fond_caisse_debut_journee', ldcGetFondCaisseDebutJournee($pdo, $businessDate)));
                    ldcUpsertDailyState($pdo, $newFond, $businessDate, $currentUserId);

                    $this->pushLegacyFlash($session, 'success', 'Fond de caisse mis à jour à ' . ldcFormatEuro($newFond) . '.');
                    break;

                case 'update_remises':
                    ldcEnsureDayIsEditable(ldcFetchDailyState($pdo, $businessDate));
                    ldcUpsertDailyRemises(
                        $pdo,
                        $businessDate,
                        trim((string) $request->request->get('num_remise_especes', '')),
                        trim((string) $request->request->get('num_remise_cheque', '')),
                        $currentUserId
                    );

                    $this->pushLegacyFlash($session, 'success', 'Numéros de remise mis à jour.');
                    break;

                case 'fin_journee':
                    $currentDailyState = ldcFetchDailyState($pdo, $businessDate);
                    if (ldcIsDailyClosed($currentDailyState)) {
                        $this->pushLegacyFlash($session, 'info', 'Cette journée est déjà clôturée.');
                    } else {
                        $currentEntries = ldcFetchEntries($pdo, $businessDate);
                        $currentTotaux = ldcGetTotaux($currentEntries);
                        $currentFondDebut = ldcGetFondCaisseDebutJournee($pdo, $businessDate);
                        $currentFondFin = ldcGetFondCaisseFinJournee($pdo, $businessDate);
                        $currentBordereau = ldcGetBordereauNumber($pdo, $businessDate);
                        $currentNumRemiseEspeces = trim((string) ($currentDailyState['num_remise_especes'] ?? ''));
                        $currentNumRemiseCheque = trim((string) ($currentDailyState['num_remise_cheque'] ?? ''));
                        $depotOn = ldcNormalizeBooleanFlag($request->request->get('depot_on', 0));
                        $depotEspece = $depotOn === 1 ? ldcNormalizeBooleanFlag($request->request->get('depot_espece', 0)) : 0;
                        $depotCheque = $depotOn === 1 ? ldcNormalizeBooleanFlag($request->request->get('depot_cheque', 0)) : 0;
                        $montantRemiseEspeces = $depotEspece === 1
                            ? ldcNormalizeMoneyInput($request->request->get('montant_remise_especes', '0'))
                            : null;
                        $montantRemiseCheque = $depotCheque === 1
                            ? ldcNormalizeMoneyInput($request->request->get('montant_remise_cheque', (string) ($currentTotaux['cheques'] ?? 0)))
                            : null;
                        $numRemiseEspecesFin = trim((string) $request->request->get('fin_num_remise_especes', $currentNumRemiseEspeces));
                        $numRemiseChequeFin = trim((string) $request->request->get('fin_num_remise_cheque', $currentNumRemiseCheque));

                        if ($depotEspece === 1) {
                            if ($montantRemiseEspeces === null || $montantRemiseEspeces <= 0) {
                                throw new \RuntimeException('Le montant du dépôt d’espèces doit être supérieur à 0.');
                            }
                            if ($numRemiseEspecesFin === '') {
                                throw new \RuntimeException('Le numéro de remise espèces est obligatoire pour clôturer avec dépôt espèces.');
                            }
                        }

                        if ($depotCheque === 1) {
                            if ($montantRemiseCheque === null || $montantRemiseCheque <= 0) {
                                throw new \RuntimeException('Le montant du dépôt de chèques doit être supérieur à 0.');
                            }
                            if ($numRemiseChequeFin === '') {
                                throw new \RuntimeException('Le numéro de remise chèque est obligatoire pour clôturer avec dépôt chèque.');
                            }
                        }

                        ldcCloseDailyState(
                            $pdo,
                            $businessDate,
                            $currentFondDebut,
                            $currentUserId,
                            [
                                'fond_caisse_fin' => $currentFondFin,
                                'bordereau_num' => $currentBordereau,
                                'depot_on' => $depotOn,
                                'depot_espece' => $depotEspece,
                                'depot_cheque' => $depotCheque,
                                'montant_remise_especes' => $montantRemiseEspeces,
                                'montant_remise_cheque' => $montantRemiseCheque,
                                'num_remise_especes' => $numRemiseEspecesFin,
                                'num_remise_cheque' => $numRemiseChequeFin,
                            ]
                        );

                        $this->pushLegacyFlash(
                            $session,
                            'success',
                            'Fin de journée validée - la journée du ' . ldcFormatDisplayDate($businessDate) . ' est désormais clôturée' . ($depotOn === 1 ? ' avec dépôt bancaire.' : '.')
                        );
                    }
                    $redirectUrl = $listingPageBaseUrl . '?' . http_build_query(['highlight_date' => $businessDate]) . '#ldc-book-' . rawurlencode($businessDate);
                    break;

                case 'fin_journee_legacy':
                    $this->pushLegacyFlash($session, 'info', 'Fin de journée - ' . count(ldcFetchEntries($pdo, $businessDate)) . ' saisie(s) enregistrée(s).');
                    break;

                case 'transfert':
                    $this->pushLegacyFlash($session, 'info', 'Transfert des fichiers déclenché.');
                    break;

                default:
                    $this->pushLegacyFlash($session, 'danger', 'Action inconnue.');
                    break;
            }
        } catch (Throwable $exception) {
            error_log('Livre de caisse action error: ' . $exception->getMessage());
            $this->pushLegacyFlash(
                $session,
                'danger',
                $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Une erreur est survenue pendant le traitement.'
            );
        }

        return $this->redirect($redirectUrl);
    }

    private function createAttachmentResponse(array $attachment, bool $forceDownload = false): Response
    {
        $mimeType = (string) ($attachment['attachment_mime'] ?? 'application/octet-stream');
        $fileName = (string) ($attachment['attachment_file_name'] ?? 'piece-jointe');
        $content = (string) ($attachment['attachment_blob'] ?? '');
        $dispositionType = $forceDownload ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = new Response($content);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', (string) strlen($content));
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition($dispositionType, $fileName));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function createAttachmentsArchiveResponse(array $attachments, string $archiveFileName): Response
    {
        if ($attachments === []) {
            throw $this->createNotFoundException('Aucune piece jointe a telecharger pour cette journee.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('L extension ZIP n est pas disponible sur le serveur.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ldczip_');
        if ($tempPath === false) {
            throw new \RuntimeException('Impossible de preparer l archive ZIP.');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Impossible de creer l archive ZIP.');
        }

        $usedNames = [];
        foreach ($attachments as $attachment) {
            $entryName = ldcBuildAttachmentArchiveEntryName($attachment, $usedNames);
            $content = (string) ($attachment['attachment_blob'] ?? '');

            if (!$zip->addFromString($entryName, $content)) {
                $zip->close();
                @unlink($tempPath);
                throw new \RuntimeException('Impossible d ajouter une piece jointe a l archive ZIP.');
            }
        }

        $zip->close();

        $safeArchiveFileName = ldcSanitizeArchivePathSegment($archiveFileName, 'pieces-jointes') . '.zip';
        $response = new BinaryFileResponse($tempPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $safeArchiveFileName);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function renderPhpTemplate(string $templatePath, array $variables): string
    {
        extract($variables, EXTR_SKIP);

        ob_start();
        require $templatePath;

        return (string) ob_get_clean();
    }

    private function getRequiredUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function createDateAwareUrlBuilder(string $baseUrl, string $businessDate): callable
    {
        return static function (array $params = []) use ($baseUrl, $businessDate): string {
            $query = array_merge(['date' => $businessDate], $params);
            $query = array_filter(
                $query,
                static fn ($value): bool => $value !== null && $value !== ''
            );

            return $baseUrl . '?' . http_build_query($query);
        };
    }

    private function pushLegacyFlash(SessionInterface $session, string $type, string $message): void
    {
        $session->set('livredecaisse_flash', [
            'type' => $type,
            'msg' => $message,
        ]);
    }

    private function popLegacyFlash(SessionInterface $session): ?array
    {
        $flash = $session->get('livredecaisse_flash');
        $session->remove('livredecaisse_flash');

        return is_array($flash) ? $flash : null;
    }

    private function getAssetVersion(string $relativePath): string
    {
        $absolutePath = $this->getParameter('kernel.project_dir') . '/' . ltrim($relativePath, '/');

        return is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
    }

    private function isPostPayloadTooLarge(Request $request): bool
    {
        if (!$request->isMethod('POST')) {
            return false;
        }

        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
        if ($contentLength <= 0) {
            return false;
        }

        $postMaxSize = $this->convertIniSizeToBytes((string) ini_get('post_max_size'));

        return $postMaxSize > 0 && $contentLength > $postMaxSize && $request->request->count() === 0 && $request->files->count() === 0;
    }

    private function convertIniSizeToBytes(string $value): int
    {
        $normalizedValue = trim($value);
        if ($normalizedValue === '') {
            return 0;
        }

        $unit = strtolower(substr($normalizedValue, -1));
        $number = (float) $normalizedValue;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }
}
