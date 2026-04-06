<?php
$totalEntries = array_reduce($agencyBooks, static fn (int $carry, array $row): int => $carry + (int) ($row['entry_count'] ?? 0), 0);
$closedCount = array_reduce($agencyBooks, static fn (int $carry, array $row): int => $carry + ((string) ($row['status'] ?? '') === 'closed' ? 1 : 0), 0);
$inProgressCount = array_reduce($agencyBooks, static fn (int $carry, array $row): int => $carry + ((string) ($row['status'] ?? '') === 'in_progress' ? 1 : 0), 0);
$resetUrl = $buildPageUrl(['date' => $businessDate]);
$currentDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $businessDate) ?: new \DateTimeImmutable('today');
$previousDayUrl = $buildPageUrl([
    'date' => $currentDate->modify('-1 day')->format('Y-m-d'),
    'departement' => $departementFilter,
    'etat' => $statusFilter,
]);
$nextDayUrl = $buildPageUrl([
    'date' => $currentDate->modify('+1 day')->format('Y-m-d'),
    'departement' => $departementFilter,
    'etat' => $statusFilter,
]);
?>
<div class="ldc-page ldc-management-page">
    <div class="panel panel-table">
        <div class="panel-header">
            <div class="panel-header-left">
                <div class="panel-header-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M9 17h.01"/><path d="M15 9h.01"/><path d="M15 13h.01"/><path d="M15 17h.01"/></svg>
                </div>
                <span class="panel-header-title">Gestion - Livres de caisse</span>
            </div>
            <div class="panel-header-meta">
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Date</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars(ldcFormatDisplayDate($businessDate), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Agences</span>
                    <span class="panel-header-meta-value"><?= (int) count($agencyBooks) ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Saisies</span>
                    <span class="panel-header-meta-value"><?= (int) $totalEntries ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Cloturees / En cours</span>
                    <span class="panel-header-meta-value"><?= (int) $closedCount ?> / <?= (int) $inProgressCount ?></span>
                </div>
            </div>
        </div>

        <div class="panel-body">
            <form method="get" class="ldc-management-filters" data-ldc-management-filters>
                <div class="ldc-management-filter-field">
                    <label for="ldc-management-date">Date</label>
                    <div class="ldc-management-date-control">
                        <a href="<?= htmlspecialchars($previousDayUrl, ENT_QUOTES, 'UTF-8') ?>" class="ldc-management-date-nav" title="Jour precedent" aria-label="Jour precedent">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                        </a>
                        <input id="ldc-management-date" type="date" name="date" value="<?= htmlspecialchars($businessDate, ENT_QUOTES, 'UTF-8') ?>" class="form-input" data-ldc-management-auto-submit>
                        <a href="<?= htmlspecialchars($nextDayUrl, ENT_QUOTES, 'UTF-8') ?>" class="ldc-management-date-nav" title="Jour suivant" aria-label="Jour suivant">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </div>
                </div>
                <div class="ldc-management-filter-field">
                    <label for="ldc-management-departement">Departement</label>
                    <select id="ldc-management-departement" name="departement" class="form-select" data-ldc-management-auto-submit>
                        <option value="">Tous les departements</option>
                        <?php foreach ($departementOptions as $departement): ?>
                            <option value="<?= htmlspecialchars((string) $departement, ENT_QUOTES, 'UTF-8') ?>"<?= $departementFilter === (string) $departement ? ' selected' : '' ?>>
                                <?= htmlspecialchars((string) $departement, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ldc-management-filter-field">
                    <label for="ldc-management-status">Etat</label>
                    <select id="ldc-management-status" name="etat" class="form-select" data-ldc-management-auto-submit>
                        <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Tous</option>
                        <option value="closed"<?= $statusFilter === 'closed' ? ' selected' : '' ?>>Cloture</option>
                        <option value="in_progress"<?= $statusFilter === 'in_progress' ? ' selected' : '' ?>>En cours</option>
                        <option value="empty"<?= $statusFilter === 'empty' ? ' selected' : '' ?>>Sans saisie</option>
                    </select>
                </div>
                <div class="ldc-management-filter-actions">
                    <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-action violet">Reinitialiser</a>
                </div>
            </form>

            <?php if ($agencyBooks === []): ?>
                <div class="ldc-history-empty">
                    Aucune agence ne correspond aux filtres selectionnes.
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="prod-table ldc-management-table">
                        <thead>
                            <tr>
                                <th>Agence</th>
                                <th>Departement</th>
                                <th class="center">Nombre de saisies</th>
                                <th class="right">Fond de fin de journee</th>
                                <th class="center">Etat</th>
                                <th class="center">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agencyBooks as $agencyBook): ?>
                                <?php
                                $status = (string) ($agencyBook['status'] ?? 'empty');
                                $statusBadgeClass = match ($status) {
                                    'closed' => 'badge-green',
                                    'in_progress' => 'badge-amber',
                                    default => 'badge-gray',
                                };
                                ?>
                                <tr>
                                    <td class="bold"><?= htmlspecialchars((string) ($agencyBook['agence'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($agencyBook['departement'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="center"><?= (int) ($agencyBook['entry_count'] ?? 0) ?></td>
                                    <td class="right bold"><?= htmlspecialchars(ldcFormatEuro((float) ($agencyBook['fond_fin'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="center">
                                        <span class="badge <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string) ($agencyBook['status_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="center">
                                        <?php if (!empty($agencyBook['has_activity']) && !empty($agencyBook['detail_url'])): ?>
                                            <a href="<?= htmlspecialchars((string) $agencyBook['detail_url'], ENT_QUOTES, 'UTF-8') ?>" class="row-btn edit ldc-history-eye" title="Voir le detail du livre de caisse">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="row-btn ldc-management-disabled-eye" title="Aucun livre de caisse pour cette agence a cette date">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19C5 19 1 12 1 12a21.77 21.77 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.95 10.95 0 0 1 12 5c7 0 11 7 11 7a21.86 21.86 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/></svg>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    (function initLivreDeCaisseManagementFilters() {
        const form = document.querySelector('[data-ldc-management-filters]');
        if (!form) {
            return;
        }

        const controls = Array.from(form.querySelectorAll('[data-ldc-management-auto-submit]'));
        let submitTimer = null;

        function scheduleSubmit() {
            if (submitTimer !== null) {
                window.clearTimeout(submitTimer);
            }

            submitTimer = window.setTimeout(function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            }, 120);
        }

        controls.forEach(function (control) {
            control.addEventListener('change', scheduleSubmit);
        });
    })();
</script>
