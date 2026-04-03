<div class="ldc-page ldc-history-page">
    <?php if ($flash && isset($flash['msg'])): ?>
        <div class="alert alert-<?= htmlspecialchars($flashClassMap[$flash['type'] ?? 'info'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) $flash['msg'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="panel panel-table">
        <div class="panel-header">
            <div class="panel-header-left">
                <div class="panel-header-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                </div>
                <span class="panel-header-title">Historique des livres de caisse</span>
            </div>
            <div class="panel-header-meta">
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Département</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars((string) ($agenceContext['departement'] !== '' ? $agenceContext['departement'] : '-'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Agence</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars((string) ($agenceContext['agence'] !== '' ? $agenceContext['agence'] : '-'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Journées</span>
                    <span class="panel-header-meta-value"><?= (int) count($historyBooks) ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Saisies</span>
                    <span class="panel-header-meta-value"><?= (int) $totalEntries ?></span>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <?php if ($historyBooks === []): ?>
                <div class="ldc-history-empty">
                    Aucun livre de caisse n'est encore enregistré.
                </div>
            <?php else: ?>
                <div class="ldc-history-list">
                    <?php foreach ($historyBooks as $book): ?>
                        <?php
                        $businessDate = (string) $book['business_date'];
                        $isClosed = (bool) ($book['is_closed'] ?? false);
                        $isHighlighted = $highlightDate !== '' && $highlightDate === $businessDate;
                        $closedAtLabel = $isClosed ? ldcFormatDisplayDateTime((string) ($book['closed_at'] ?? '')) : '';
                        $viewUrl = $ldcPageBaseUrl . '?date=' . rawurlencode($businessDate);
                        $downloadAttachmentsUrl = $buildPageUrl([
                            'date' => $businessDate,
                            'download_attachments' => 1,
                        ]);
                        $attachmentCount = (int) ($book['attachment_count'] ?? 0);
                        $accordionTitle = sprintf(
                            'LDC - %s - %s | %s',
                            (string) ($agenceContext['departement'] !== '' ? $agenceContext['departement'] : '-'),
                            (string) ($agenceContext['agence'] !== '' ? $agenceContext['agence'] : '-'),
                            ldcFormatDisplayDate($businessDate, 'd-m-Y')
                        );
                        ?>
                        <details
                            class="ldc-history-item<?= $isClosed ? ' is-closed' : '' ?><?= $isHighlighted ? ' is-highlighted' : '' ?>"
                            id="ldc-book-<?= htmlspecialchars($businessDate, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $isHighlighted ? ' open' : '' ?>
                        >
                            <summary class="ldc-history-summary">
                                <div class="ldc-history-summary-main">
                                    <span class="ldc-history-toggle" aria-hidden="true"></span>
                                    <div class="ldc-history-title-wrap">
                                        <span class="ldc-history-title"><?= htmlspecialchars($accordionTitle, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge badge-gray"><?= (int) $book['entry_count'] ?> saisie<?= (int) $book['entry_count'] > 1 ? 's' : '' ?></span>
                                        <?php if ($isClosed): ?>
                                            <span class="ldc-history-closed-badge" title="<?= htmlspecialchars($closedAtLabel !== '' && $closedAtLabel !== '-' ? 'Clôturée le ' . $closedAtLabel : 'Journée clôturée', ENT_QUOTES, 'UTF-8') ?>">
                                                Clôturée
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ldc-history-summary-side">
                                    <div class="ldc-history-amount-card">
                                        <span class="ldc-history-amount-label">Fonds fin de journée</span>
                                        <strong class="ldc-history-amount-value"><?= htmlspecialchars(ldcFormatEuro((float) $book['fond_fin']), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="ldc-history-header-actions">
                                        <?php if ($attachmentCount > 0): ?>
                                            <a href="<?= htmlspecialchars($downloadAttachmentsUrl, ENT_QUOTES, 'UTF-8') ?>" class="ldc-history-download" title="Télécharger toutes les pièces jointes" onclick="event.stopPropagation();">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v10"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
                                                <span><?= $attachmentCount ?> fichier<?= $attachmentCount > 1 ? 's' : '' ?></span>
                                            </a>
                                        <?php else: ?>
                                            <span class="ldc-history-download is-disabled">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v10"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
                                                <span>0 fichier</span>
                                            </span>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>" class="row-btn edit ldc-history-eye" title="Voir le livre de caisse" onclick="event.stopPropagation();">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    </div>
                                </div>
                            </summary>

                            <div class="ldc-history-content">
                                <?php if (($book['entries'] ?? []) === []): ?>
                                    <div class="ldc-history-empty is-inline">
                                        Aucune saisie enregistrée pour cette journée.
                                    </div>
                                <?php else: ?>
                                    <div class="table-scroll ldc-history-table-scroll">
                                        <table class="prod-table ldc-history-table">
                                            <thead>
                                                <tr>
                                                    <th>Date / heure</th>
                                                    <th>Chrono</th>
                                                    <th>Type</th>
                                                    <th>Risque</th>
                                                    <th>Nom</th>
                                                    <th>Prénom</th>
                                                    <th>Encaissement</th>
                                                    <th class="right">Montant</th>
                                                    <th class="center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($book['entries'] as $entry): ?>
                                                    <?php
                                                    $typeBadgeClass = str_starts_with((string) $entry['type_affaire'], 'AN') ? 'badge-green' : 'badge-red';
                                                    $encaissementBadgeClass = match ((string) $entry['type_encaissement']) {
                                                        'Espèces' => 'badge-green',
                                                        'Chèque' => 'badge-blue',
                                                        'CB' => 'badge-amber',
                                                        'Comptant à Prélever' => 'badge-violet',
                                                        'Comptant Offert' => 'badge-red',
                                                        'Appel de Cotisation' => 'badge-gray',
                                                        default => 'badge-gray',
                                                    };
                                                    $entryViewUrl = $ldcPageBaseUrl . '?date=' . rawurlencode($businessDate);
                                                    $entryEditUrl = $ldcPageBaseUrl . '?date=' . rawurlencode($businessDate) . '&edit=' . (int) $entry['id'];
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars((string) $entry['date_saisie_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="mono"><?= htmlspecialchars((string) $entry['chrono'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><span class="badge <?= htmlspecialchars($typeBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $entry['type_affaire'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                        <td><?= htmlspecialchars((string) $entry['risque'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="bold"><?= htmlspecialchars((string) $entry['nom_adherent'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $entry['prenom_adherent'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><span class="badge <?= htmlspecialchars($encaissementBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $entry['type_encaissement'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                        <td class="right bold"><?= htmlspecialchars(ldcFormatEuro((float) $entry['montant']), ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="center">
                                                            <div class="ldc-history-actions">
                                                                <a href="<?= htmlspecialchars($entryViewUrl, ENT_QUOTES, 'UTF-8') ?>" class="ldc-history-action is-view">Voir</a>
                                                                <?php if ($isClosed): ?>
                                                                    <span class="ldc-history-action is-disabled">Clôturé</span>
                                                                <?php else: ?>
                                                                    <a href="<?= htmlspecialchars($entryEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="ldc-history-action is-edit">Editer</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
