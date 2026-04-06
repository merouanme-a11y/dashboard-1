<?php
$agency = (array) ($detail['agency'] ?? []);
$entries = (array) ($detail['entries'] ?? []);
$totaux = (array) ($detail['totaux'] ?? []);
$isClosed = (bool) ($detail['is_closed'] ?? false);
$closedAt = (string) ($detail['closed_at'] ?? '');
?>
<div class="ldc-page ldc-management-detail-page">
    <div class="panel panel-table">
        <div class="panel-header">
            <div class="panel-header-left">
                <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="row-btn edit ldc-management-back">Retour</a>
                <div>
                    <span class="panel-header-title">Detail - <?= htmlspecialchars((string) ($agency['label'] ?? 'Agence'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="panel-header-meta">
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Date</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars(ldcFormatDisplayDate($businessDate), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Etat</span>
                    <span class="panel-header-meta-value"><?= $isClosed ? 'Cloture' : 'En cours' ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Saisies</span>
                    <span class="panel-header-meta-value"><?= (int) ($detail['entry_count'] ?? 0) ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Fond fin</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars(ldcFormatEuro((float) ($detail['fond_fin'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <div class="ldc-management-summary-grid">
                <div class="caisse-card is-especes">
                    <span class="caisse-card-title">Especes</span>
                    <strong class="caisse-card-amount"><?= htmlspecialchars(ldcFormatEuro((float) ($totaux['especes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="caisse-card is-cheque">
                    <span class="caisse-card-title">Cheques</span>
                    <strong class="caisse-card-amount"><?= htmlspecialchars(ldcFormatEuro((float) ($totaux['cheques'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="caisse-card is-cb">
                    <span class="caisse-card-title">CB</span>
                    <strong class="caisse-card-amount"><?= htmlspecialchars(ldcFormatEuro((float) ($totaux['cb'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="caisse-card">
                    <span class="caisse-card-title">Total</span>
                    <strong class="caisse-card-amount"><?= htmlspecialchars(ldcFormatEuro((float) ($totaux['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>

            <?php if ($closedAt !== ''): ?>
                <div class="ldc-management-closed-note">
                    Journee cloturee le <?= htmlspecialchars(ldcFormatDisplayDateTime($closedAt), ENT_QUOTES, 'UTF-8') ?>.
                </div>
            <?php endif; ?>

            <?php if ($entries === []): ?>
                <div class="ldc-history-empty">
                    Aucun detail de saisie n'est disponible pour cette agence a cette date.
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="prod-table ldc-history-table">
                        <thead>
                            <tr>
                                <th>Date / heure</th>
                                <th>Chrono</th>
                                <th>Type</th>
                                <th>Risque</th>
                                <th>Nom</th>
                                <th>Prenom</th>
                                <th>Encaissement</th>
                                <th class="right">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($entry['date_saisie_display'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="mono"><?= htmlspecialchars((string) ($entry['chrono'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($entry['type_affaire'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($entry['risque'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="bold"><?= htmlspecialchars((string) ($entry['nom_adherent'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($entry['prenom_adherent'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($entry['type_encaissement'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="right bold"><?= htmlspecialchars(ldcFormatEuro((float) ($entry['montant'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
