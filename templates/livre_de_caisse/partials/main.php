<div class="ldc-page">
    <?php if ($flash && isset($flash['msg'])): ?>
        <div class="alert alert-<?= htmlspecialchars($flashClassMap[$flash['type'] ?? 'info'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string) $flash['msg'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($isDayClosed): ?>
        <div class="alert alert-success ldc-closure-alert">
            Journée clôturée<?= $closedAtLabel !== '' && $closedAtLabel !== '-' ? ' le ' . htmlspecialchars($closedAtLabel, ENT_QUOTES, 'UTF-8') : '' ?>. Cette date est désormais en lecture seule.
        </div>
    <?php endif; ?>

    <div class="panel panel-caisse">
        <div class="panel-header">
            <div class="panel-header-left">
                <div class="caisse-stats">
                    <div class="caisse-stat-block">
                        <div class="caisse-stat-label">Fonds de caisse en début de journée</div>
                        <button type="button" id="edit-fond-caisse" class="caisse-stat-value caisse-stat-button<?= $isDayClosed ? ' is-disabled' : '' ?>" data-fond-caisse-value="<?= htmlspecialchars(number_format($fondDebut, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>" <?= $isDayClosed ? 'disabled' : '' ?>>
                            <?= htmlspecialchars(ldcFormatEuro($fondDebut), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="caisse-stat-block is-hidden" aria-hidden="true">
                        <div class="caisse-stat-label">Fonds de caisse en fin de journée</div>
                        <div class="caisse-stat-value"><?= htmlspecialchars(ldcFormatEuro($fondFin), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
            <div class="panel-header-meta">
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Agence</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars((string) $agenceContext['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Date de saisie</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars(ldcCurrentDateLabel($businessDate), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Bordereau</span>
                    <span class="panel-header-meta-value">N° <?= htmlspecialchars((string) $currentBordereauNum, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="panel-header-meta-item">
                    <span class="panel-header-meta-label">Prochain chrono</span>
                    <span class="panel-header-meta-value"><?= htmlspecialchars((string) $nextChrono, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="caisse-stats">
                <div class="caisse-stat-block is-hidden" aria-hidden="true">
                    <div class="caisse-stat-label">Fonds de caisse en début de journée</div>
                    <button type="button" id="edit-fond-caisse-legacy" class="caisse-stat-value caisse-stat-button<?= $isDayClosed ? ' is-disabled' : '' ?>" data-fond-caisse-value="<?= htmlspecialchars(number_format($fondDebut, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" <?= $isDayClosed ? 'disabled' : '' ?>>
                        <?= htmlspecialchars(ldcFormatEuro($fondDebut), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <div class="caisse-stat-divider"></div>
                <div class="caisse-stat-block">
                    <div class="caisse-stat-label">Fonds de caisse en fin de journée</div>
                    <div class="caisse-stat-value"><?= htmlspecialchars(ldcFormatEuro($fondFin), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>

        <div class="caisse-card-grid">
            <?php foreach ($caisseCards as $card): ?>
                <div class="caisse-card <?= htmlspecialchars((string) $card['class'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="caisse-card-title"><?= htmlspecialchars((string) $card['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="caisse-card-amount"><?= htmlspecialchars(ldcFormatEuro((float) $card['amount']), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="caisse-card-meta"><?= (int) $card['count'] ?> saisie<?= ((int) $card['count']) > 1 ? 's' : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" class="caisse-remise-form" id="caisse-remise-form">
            <input type="hidden" name="ldc_action" value="update_remises">
            <table class="caisse-table">
                <thead>
                    <tr>
                        <th class="left">Type</th>
                        <th class="center">Nb saisies</th>
                        <th class="right">Montant</th>
                        <th class="left">N&#176; Remise</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caisseRows as $row): ?>
                        <tr>
                            <td class="bold left"><?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="center"><?= (int) $row['count'] ?></td>
                            <td class="right bold"><?= htmlspecialchars(ldcFormatEuro((float) $row['amount']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="left">
                                <?php if (!empty($row['remise_field'])): ?>
                                    <input
                                        type="text"
                                        name="<?= htmlspecialchars((string) $row['remise_field'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="form-input remise-input"
                                        value="<?= htmlspecialchars((string) ($row['remise_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="<?= ((string) ($row['remise_field'] ?? '')) === 'num_remise_cheque' ? 'N&#176; remise Cheque' : 'N&#176; remise Especes' ?>"
                                        data-auto-submit-remise
                                        <?= $isDayClosed ? 'disabled' : '' ?>
                                    >
                                <?php else: ?>
                                    <span class="oa-no">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td class="left">TOTAL JOUR</td>
                        <td class="center"><?= (int) $totaux['nb_total'] ?></td>
                        <td class="right"><?= htmlspecialchars(ldcFormatEuro((float) $totaux['total']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="left"><span class="oa-no">-</span></td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>

    <div class="actions-bar">
        <button
            type="button"
            class="btn-action filled-green<?= $isDayClosed ? ' is-disabled' : '' ?>"
            id="open-nouveau-reglement"
            data-open-url="<?= htmlspecialchars($buildPageUrl(['open' => 1]), ENT_QUOTES, 'UTF-8') ?>"
            <?= $isDayClosed ? 'disabled' : '' ?>
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Nouveau Règlement
        </button>

        <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" class="fin-journee-form" data-fond-fin-label="<?= htmlspecialchars(ldcFormatEuro($fondFin), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="ldc_action" value="fin_journee">
            <input type="hidden" name="depot_on" value="0">
            <input type="hidden" name="depot_espece" value="0">
            <input type="hidden" name="depot_cheque" value="0">
            <input type="hidden" name="montant_remise_especes" value="">
            <input type="hidden" name="montant_remise_cheque" value="">
            <input type="hidden" name="fin_num_remise_especes" value="">
            <input type="hidden" name="fin_num_remise_cheque" value="">
            <button type="submit" class="btn-action red<?= $isDayClosed ? ' is-disabled' : '' ?>" <?= $isDayClosed ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <?= $isDayClosed ? 'Fin de journée validée' : 'Fin de journée' ?>
            </button>
        </form>

        <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="ldc_action" value="transfert">
            <button type="submit" class="btn-action blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1"/><path d="M12 12V4m0 0l-4 4m4-4l4 4"/></svg>
                Transfert fichiers
            </button>
        </form>

        <button type="button" class="btn-action amber" data-print-filter="an">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2"/><rect x="7" y="13" width="10" height="8" rx="1"/></svg>
            Imprimer AN
        </button>

        <button type="button" class="btn-action rose" data-print-filter="impaye">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2"/><rect x="7" y="13" width="10" height="8" rx="1"/></svg>
            Imprimer Impayé
        </button>

        <button type="button" class="btn-action violet" data-print-filter="all">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2"/><rect x="7" y="13" width="10" height="8" rx="1"/></svg>
            Imprimer tout
        </button>
    </div>

    <div id="saisie-form" class="panel <?= $isEditing ? 'panel-edit' : 'panel-form' ?><?= $showSaisieForm ? '' : ' is-hidden' ?>">
        <div class="panel-header">
            <div class="panel-header-left">
                <div class="panel-header-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                </div>
                <span class="panel-header-title"><?= $isEditing ? 'Modifier le règlement' : 'Saisie des règlements' ?></span>
            </div>
            <?php if ($isEditing): ?>
                <span class="badge badge-amber">Modification - Chrono #<?= htmlspecialchars((string) $editEntry['chrono'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>

        <div class="panel-body">
            <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
                <input type="hidden" name="ldc_action" value="<?= $isEditing ? 'modifier' : 'nouveau' ?>">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editEntry['id'] ?>">
                <?php endif; ?>
                <input type="hidden" name="fond_caisse_debut_journee" id="fond_caisse_debut_journee" value="<?= htmlspecialchars(number_format($fondDebut, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="fond_caisse_confirme" id="fond_caisse_confirme" value="0">

                <div class="form-grid cols-2" style="margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label required">Nom Adh&eacute;rent <span class="star">*</span></label>
                        <input type="text" name="nom_adherent" class="form-input required-border" required value="<?= htmlspecialchars((string) ($editEntry['nom_adherent'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Pr&eacute;nom Adh&eacute;rent <span class="star">*</span></label>
                        <input type="text" name="prenom_adherent" class="form-input required-border" required value="<?= htmlspecialchars((string) ($editEntry['prenom_adherent'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-block" style="margin-bottom:1rem;">
                    <div class="form-section-title">Encaissement</div>
                    <div class="form-grid cols-6 encaissement-grid">
                        <div class="form-group">
                            <label class="form-label required">Type d'Affaire <span class="star">*</span></label>
                            <select name="type_affaire" id="type_affaire" class="form-select" required>
                                <option value="">- S&eacute;lectionner -</option>
                                <?php foreach (ldcGetTypesAffaire() as $typeAffaire): ?>
                                    <option value="<?= htmlspecialchars($typeAffaire, ENT_QUOTES, 'UTF-8') ?>" <?= (($editEntry['type_affaire'] ?? '') === $typeAffaire) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($typeAffaire, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="type-affaire-helper" class="type-affaire-helper<?= $currentTypeAffaireHelper === '' ? ' is-hidden' : '' ?>">
                                <?= htmlspecialchars($currentTypeAffaireHelper, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Risque <span class="star">*</span></label>
                            <select name="risque" id="risque" class="form-select" required <?= $currentTypeAffaire === '' ? 'disabled' : '' ?>>
                                <option value="">- S&eacute;lectionner -</option>
                                <?php foreach (ldcGetRisques() as $risque): ?>
                                    <option value="<?= htmlspecialchars($risque, ENT_QUOTES, 'UTF-8') ?>" <?= (($editEntry['risque'] ?? '') === $risque) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($risque, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Type Encaissement <span class="star">*</span></label>
                            <select name="type_encaissement" id="type_encaissement" class="form-select" required <?= (($editEntry['risque'] ?? '') === '') ? 'disabled' : '' ?>>
                                <option value="">- S&eacute;lectionner -</option>
                                <?php foreach ($currentTypeEncaissementOptions as $typeEncaissement): ?>
                                    <option value="<?= htmlspecialchars($typeEncaissement, ENT_QUOTES, 'UTF-8') ?>" <?= (($editEntry['type_encaissement'] ?? '') === $typeEncaissement) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($typeEncaissement, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Montant (&euro;) <span class="star">*</span></label>
                            <input type="number" step="0.01" min="0" name="montant" class="form-input text-right required-border" required placeholder="0,00" value="<?= htmlspecialchars(isset($editEntry['montant']) ? number_format((float) $editEntry['montant'], 2, '.', '') : '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <?php $currentEncaissementLabel = (string) ($editEntry['type_encaissement'] ?? ''); $showChequeBox = stripos($currentEncaissementLabel, 'CB') !== false || stripos($currentEncaissementLabel, 'Ch') === 0; ?>
                        <div id="cheque-box" class="form-group cheque-box<?= $showChequeBox ? ' is-required' : ' is-hidden' ?>">
                            <label class="form-label">N&deg; Ch&egrave;que / Transaction CB</label>
                            <input type="text" name="num_cheque" class="form-input" <?= $showChequeBox ? 'required' : 'disabled' ?> value="<?= htmlspecialchars((string) ($editEntry['num_cheque'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group is-required">
                            <label class="form-label">Date r&eacute;glement</label>
                            <input type="date" name="date_reglement" id="date_reglement" class="form-input bg-muted" required value="<?= htmlspecialchars((string) ($editEntry['date_reglement'] ?? $businessDate), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="conditional-section<?= $showSection(['num_contrat', 'mois_anticipation']) ? '' : ' is-hidden' ?>" data-conditional-section style="margin-bottom:1rem;">
                    <div class="form-grid cols-2">
                        <div class="form-group<?= $showField('num_contrat') ? '' : ' is-hidden' ?><?= $requireField('num_contrat') ? ' is-required' : '' ?>" data-conditional-field="num_contrat">
                            <label class="form-label">N&deg; Contrat</label>
                            <input type="text" name="num_contrat" class="form-input bg-muted" <?= $showField('num_contrat') ? '' : 'disabled' ?> <?= $requireField('num_contrat') ? 'required' : '' ?> value="<?= htmlspecialchars((string) ($editEntry['num_contrat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group<?= $showField('mois_anticipation') ? '' : ' is-hidden' ?><?= $requireField('mois_anticipation') ? ' is-required' : '' ?>" data-conditional-field="mois_anticipation">
                            <label class="form-label">Mois r&eacute;gl&eacute;s par anticipation</label>
                            <div class="month-picker" data-month-picker>
                                <input type="hidden" name="mois_anticipation" data-month-picker-input value="<?= htmlspecialchars(implode(',', $currentAnticipationValues), ENT_QUOTES, 'UTF-8') ?>" <?= $showField('mois_anticipation') ? '' : 'disabled' ?> <?= $requireField('mois_anticipation') ? 'required' : '' ?>>
                                <button type="button" class="multi-select-trigger" data-month-picker-trigger aria-expanded="false" <?= $showField('mois_anticipation') ? '' : 'disabled' ?>>
                                    <span data-month-picker-label><?= $currentAnticipationValues === [] ? 'S&eacute;lectionner un ou plusieurs mois' : htmlspecialchars(ldcFormatAnticipationSummary($currentAnticipationValues), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="multi-select-caret">&#9662;</span>
                                </button>
                                <div class="multi-select-panel" data-month-picker-menu>
                                    <div class="multi-select-panel-header">S&eacute;lection multiple Mois / Ann&eacute;e</div>
                                    <div class="multi-select-options" data-month-picker-options>
                                        <?php foreach ($currentAnticipationOptions as $option): ?>
                                            <?php $isChecked = in_array($option['value'], $currentAnticipationValues, true); ?>
                                            <label class="multi-select-option">
                                                <input type="checkbox" value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $isChecked ? 'checked' : '' ?> <?= $showField('mois_anticipation') ? '' : 'disabled' ?>>
                                                <span><?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="conditional-section<?= $showSection(['saisie_oa', 'reglement_avis', 'avenant', 'regul_impaye', 'regul_mise_demeure']) ? '' : ' is-hidden' ?>" data-conditional-section style="margin-bottom:1rem;">
                    <div class="options-box">
                        <div class="options-box-title">Options</div>
                        <div class="options-grid">
                            <?php
                            $checkboxes = [
                                ['saisie_oa', 'Saisie dans Open Assur'],
                                ['reglement_avis', "Règlement avis d'échéance"],
                                ['avenant', 'Avenant'],
                                ['regul_impaye', 'Régul. Impayés'],
                                ['regul_mise_demeure', 'Régul. mise en demeure'],
                            ];
                            foreach ($checkboxes as [$name, $label]):
                                $visible = $showField($name);
                                $checked = ($editEntry[$name] ?? 'Non') === 'Oui';
                            ?>
                                <label class="checkbox-label<?= $visible ? '' : ' is-hidden' ?>" data-conditional-field="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="checkbox" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" <?= $checked ? 'checked' : '' ?> <?= $visible ? '' : 'disabled' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="conditional-section<?= $showSection(['num_adhesion', 'date_effet']) ? '' : ' is-hidden' ?>" data-conditional-section style="margin-bottom:1rem;">
                    <div class="form-section-title">Adhésion et effet</div>
                    <div class="form-grid cols-2">
                        <div class="form-group<?= $showField('num_adhesion') ? '' : ' is-hidden' ?><?= $requireField('num_adhesion') ? ' is-required' : '' ?>" data-conditional-field="num_adhesion">
                            <label class="form-label">N° Adhésion</label>
                            <input type="text" name="num_adhesion" class="form-input bg-muted" <?= $showField('num_adhesion') ? '' : 'disabled' ?> <?= $requireField('num_adhesion') ? 'required' : '' ?> value="<?= htmlspecialchars((string) ($editEntry['num_adhesion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group<?= $showField('date_effet') ? '' : ' is-hidden' ?><?= $requireField('date_effet') ? ' is-required' : '' ?>" data-conditional-field="date_effet">
                            <label class="form-label">Date effet du contrat</label>
                            <input type="date" name="date_effet" class="form-input bg-muted" <?= $showField('date_effet') ? '' : 'disabled' ?> <?= $requireField('date_effet') ? 'required' : '' ?> value="<?= htmlspecialchars((string) ($editEntry['date_effet'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="conditional-section<?= $showSection(['formule_produit', 'mandataire']) ? '' : ' is-hidden' ?>" data-conditional-section style="margin-bottom:1rem;">
                    <div class="form-section-title">Produit et réseau</div>
                    <div class="form-grid cols-2">
                        <div class="form-group<?= $showField('formule_produit') ? '' : ' is-hidden' ?><?= $requireField('formule_produit') ? ' is-required' : '' ?>" data-conditional-field="formule_produit">
                            <label class="form-label">Formule / Produit</label>
                            <input type="text" name="formule_produit" class="form-input bg-muted" <?= $showField('formule_produit') ? '' : 'disabled' ?> <?= $requireField('formule_produit') ? 'required' : '' ?> value="<?= htmlspecialchars((string) ($editEntry['formule_produit'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group<?= $showField('mandataire') ? '' : ' is-hidden' ?><?= $requireField('mandataire') ? ' is-required' : '' ?>" data-conditional-field="mandataire">
                            <label class="form-label">Mandataire / Agence / Courtier</label>
                            <input type="text" name="mandataire" class="form-input bg-muted" <?= $showField('mandataire') ? '' : 'disabled' ?> <?= $requireField('mandataire') ? 'required' : '' ?> value="<?= htmlspecialchars((string) ($editEntry['mandataire'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="conditional-section<?= $showSection(['dsu']) ? '' : ' is-hidden' ?>" data-conditional-section style="margin-bottom:1rem;">
                    <div class="form-section-title">Informations complémentaires</div>
                    <div class="form-grid cols-2">
                        <div class="form-group<?= $showField('dsu') ? '' : ' is-hidden' ?>" data-conditional-field="dsu">
                            <label class="form-label">DSU</label>
                            <input type="text" name="dsu" class="form-input bg-muted" <?= $showField('dsu') ? '' : 'disabled' ?> value="<?= htmlspecialchars((string) ($editEntry['dsu'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-block attachment-block" style="margin-bottom:1rem;">
                    <div class="form-section-title">Pièces jointes</div>
                    <div class="attachment-dropzone" data-attachment-dropzone tabindex="0" role="button" aria-label="Ajouter des pièces jointes">
                        <input
                            type="file"
                            id="pieces-jointes-input"
                            name="pieces_jointes[]"
                            class="is-hidden"
                            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                            multiple
                            data-attachment-input
                        >
                        <input
                            type="file"
                            id="pieces-jointes-camera-input"
                            class="is-hidden"
                            accept="image/*"
                            capture="environment"
                            data-attachment-camera-input
                        >
                        <div class="attachment-dropzone-icon">+</div>
                        <div class="attachment-dropzone-title">Glisser-deposer vos fichiers ici</div>
                        <div class="attachment-dropzone-text">
                            Formats acceptes : <?= htmlspecialchars($attachmentAcceptedFormats, ENT_QUOTES, 'UTF-8') ?>.
                            Taille max : <?= htmlspecialchars($attachmentMaxSizeLabel, ENT_QUOTES, 'UTF-8') ?> par fichier.
                        </div>
                        <div class="attachment-dropzone-actions">
                            <button type="button" class="btn btn-secondary" data-attachment-trigger>Ajouter des fichiers</button>
                            <button type="button" class="btn btn-secondary" data-attachment-camera-trigger>Prendre une photo</button>
                        </div>
                    </div>

                    <div class="attachment-selected-list is-hidden" data-attachment-selected-list></div>

                    <?php if ($isEditing): ?>
                        <div class="attachment-existing">
                            <div class="attachment-existing-title">Pièces jointes deja enregistrees</div>
                            <?php if ($editAttachments === []): ?>
                                <div class="attachment-empty">Aucune pièce jointe enregistrée pour ce règlement.</div>
                            <?php else: ?>
                                <div class="attachment-existing-list">
                                    <?php foreach ($editAttachments as $attachment): ?>
                                        <?php
                                        $attachmentUrl = $buildPageUrl(['attachment' => (int) $attachment['id']]);
                                        $attachmentDownloadUrl = $buildPageUrl([
                                            'attachment' => (int) $attachment['id'],
                                            'download' => 1,
                                        ]);
                                        $attachmentTypeLabel = match ((string) $attachment['attachment_mime']) {
                                            'image/jpeg' => 'JPG',
                                            'image/png' => 'PNG',
                                            'application/pdf' => 'PDF',
                                            default => 'Fichier',
                                        };
                                        ?>
                                        <div class="attachment-item">
                                            <div class="attachment-item-main">
                                                <span class="badge badge-gray"><?= htmlspecialchars($attachmentTypeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="attachment-item-link">
                                                    <?= htmlspecialchars((string) $attachment['attachment_file_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                                <span class="attachment-item-size"><?= htmlspecialchars(ldcFormatAttachmentSize((int) $attachment['attachment_size']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="attachment-item-actions">
                                                <a href="<?= htmlspecialchars($attachmentDownloadUrl, ENT_QUOTES, 'UTF-8') ?>" class="row-btn edit" title="Telecharger">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
                                                </a>
                                                <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" class="form-delete" data-confirm-message="Voulez-vous vraiment supprimer cette piece jointe ?">
                                                    <input type="hidden" name="ldc_action" value="supprimer_piece_jointe">
                                                    <input type="hidden" name="attachment_id" value="<?= (int) $attachment['id'] ?>">
                                                    <button type="submit" class="row-btn delete" title="Supprimer la pièce jointe">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <?php if ($isEditing): ?>
                        <button type="submit" class="btn btn-warning">Modifier</button>
                        <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Annuler</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary" id="valider-reglement-submit">Valider Règlement</button>
                        <button type="reset" class="btn btn-secondary">Effacer</button>
                    <?php endif; ?>
                    <span class="form-hint"><span class="star">*</span> Champs obligatoires</span>
                </div>
            </form>
        </div>
    </div>

    <?php if ($entries !== []): ?>
        <div class="panel panel-table">
            <div class="panel-header">
                <div class="panel-header-left">
                    <div class="panel-header-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
                    </div>
                    <span class="panel-header-title">Production du jour</span>
                </div>
                <div class="table-tools">
                    <button type="button" id="toggle-table-columns" class="btn-table-toggle" aria-expanded="false">
                        Afficher toutes les colonnes
                    </button>
                    <span class="badge badge-gray"><?= count($entries) ?> saisie<?= count($entries) > 1 ? 's' : '' ?></span>
                </div>
            </div>

            <div class="table-scroll" id="prod-table-scroll">
                <table class="prod-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date / heure</th>
                            <th>Chrono</th>
                            <th>Type</th>
                            <th>Risque</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th class="center">OA</th>
                            <th>Encaissement</th>
                            <th class="right">Montant</th>
                            <th>N° Contrat</th>
                            <?php foreach ($extendedTableColumns as $column): ?>
                                <th class="col-extended"><?= htmlspecialchars((string) $column['label'], ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                            <th class="center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $index => $entry): ?>
                            <?php
                            $typeBadgeClass = str_starts_with((string) $entry['type_affaire'], 'AN') ? 'badge-green' : 'badge-red';
                            $encaissementBadgeClass = match ($entry['type_encaissement']) {
                                'Espèces' => 'badge-green',
                                'Chèque' => 'badge-blue',
                                'CB' => 'badge-amber',
                                'Comptant à Prélever' => 'badge-violet',
                                'Comptant Offert' => 'badge-red',
                                'Appel de Cotisation' => 'badge-gray',
                                default => 'badge-gray',
                            };
                            $typeAffaireGroup = (str_starts_with((string) $entry['type_affaire'], 'AN') || (string) $entry['type_affaire'] === 'COMPLEMENT') ? 'an' : 'impaye';
                            ?>
                            <tr data-type-affaire-group="<?= htmlspecialchars($typeAffaireGroup, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="mono" style="color: var(--text-muted);"><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars((string) $entry['date_saisie_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono"><?= htmlspecialchars((string) $entry['chrono'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $typeBadgeClass ?>"><?= htmlspecialchars((string) $entry['type_affaire'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string) $entry['risque'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="bold"><?= htmlspecialchars((string) $entry['nom_adherent'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $entry['prenom_adherent'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="center">
                                    <?php if (($entry['saisie_oa'] ?? 'Non') === 'Oui'): ?>
                                        <span class="oa-yes">&#10003;</span>
                                    <?php else: ?>
                                        <span class="oa-no">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $encaissementBadgeClass ?>"><?= htmlspecialchars((string) $entry['type_encaissement'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="right bold"><?= htmlspecialchars(ldcFormatEuro((float) $entry['montant']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="mono" style="color: var(--text-muted);"><?= htmlspecialchars(((string) ($entry['num_contrat'] ?? '')) !== '' ? (string) $entry['num_contrat'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($extendedTableColumns as $column): ?>
                                    <td class="col-extended"><?= htmlspecialchars($formatTableValue($entry, (string) $column['key']), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                                <td class="center">
                                    <?php if ($isDayClosed): ?>
                                        <span class="badge badge-green">Clôturé</span>
                                    <?php else: ?>
                                        <div class="row-actions">
                                            <a href="<?= htmlspecialchars($buildPageUrl(['edit' => (int) $entry['id']]), ENT_QUOTES, 'UTF-8') ?>" class="row-btn edit" title="Modifier">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </a>
                                            <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" class="form-delete" data-confirm-message="Voulez-vous vraiment supprimer ce reglement et ses pieces jointes ?">
                                                <input type="hidden" name="ldc_action" value="supprimer">
                                                <input type="hidden" name="delete_id" value="<?= (int) $entry['id'] ?>">
                                                <button type="submit" class="row-btn delete" title="Supprimer">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" id="fond-caisse-update-form" class="is-hidden">
        <input type="hidden" name="ldc_action" value="update_fond_caisse">
        <input type="hidden" name="fond_caisse_debut_journee" id="fond_caisse_update_value" value="<?= htmlspecialchars(number_format($fondDebut, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
    </form>

    <div id="fond-caisse-modal" class="modal-overlay is-hidden" aria-hidden="true">
        <div class="modal-backdrop" data-close-ldc-modal="fond"></div>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="fond-caisse-modal-title">
            <button type="button" class="modal-close" data-close-ldc-modal="fond" aria-label="Fermer la fenêtre">&times;</button>
            <div class="modal-header">
                <div class="modal-header-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="6" width="18" height="12" rx="3"></rect>
                        <path d="M16 12h.01"></path>
                        <path d="M7 10h4"></path>
                        <path d="M7 14h6"></path>
                    </svg>
                </div>
                <div class="modal-header-copy">
                    <div class="modal-title" id="fond-caisse-modal-title">Fonds de caisse en d&eacute;but de journ&eacute;e</div>
                    <p class="modal-text">Confirmez ou ajustez le montant avant de poursuivre la saisie.</p>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="fond-caisse-modal-input">Montant (&euro;) <span class="star">*</span></label>
                <input type="number" step="0.01" min="0" id="fond-caisse-modal-input" class="form-input text-right" value="<?= htmlspecialchars(number_format($fondDebut, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <p class="modal-note">Ce montant restera modifiable &agrave; tout moment depuis le header de caisse.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="fond-caisse-cancel">Annuler</button>
                <button type="button" class="btn btn-primary" id="fond-caisse-confirm">Confirmer</button>
            </div>
        </div>
    </div>

    <div id="fin-journee-modal" class="modal-overlay is-hidden" aria-hidden="true">
        <div class="modal-backdrop" data-close-ldc-modal="fin"></div>
        <div class="modal-card modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="fin-journee-modal-title">
            <button type="button" class="modal-close" data-close-ldc-modal="fin" aria-label="Fermer la fenêtre">&times;</button>
            <div class="modal-header">
                <div class="modal-header-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2"></path>
                    </svg>
                </div>
                <div class="modal-header-copy">
                    <div class="modal-title" id="fin-journee-modal-title">Cl&ocirc;ture de journ&eacute;e</div>
                    <p class="modal-text">Validez le traitement de fin de journ&eacute;e avant de verrouiller cette date.</p>
                </div>
            </div>

            <div class="modal-kpi-grid">
                <div class="modal-kpi">
                    <span class="modal-kpi-label">Fonds fin de journ&eacute;e</span>
                    <strong class="modal-kpi-value" id="fin-journee-fond-fin"><?= htmlspecialchars(ldcFormatEuro($fondFin), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="modal-kpi">
                    <span class="modal-kpi-label">Total esp&egrave;ces</span>
                    <strong class="modal-kpi-value" id="fin-journee-total-especes"><?= htmlspecialchars(ldcFormatEuro((float) $totaux['especes']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="modal-kpi">
                    <span class="modal-kpi-label">Total ch&egrave;ques</span>
                    <strong class="modal-kpi-value" id="fin-journee-total-cheques"><?= htmlspecialchars(ldcFormatEuro((float) $totaux['cheques']), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>

            <div id="fin-journee-modal-error" class="modal-feedback is-hidden" role="alert"></div>

            <div class="modal-section">
                <div class="modal-section-title">Mode de cl&ocirc;ture</div>
                <div class="modal-choice-grid">
                    <button type="button" class="modal-choice-card is-active" data-fin-mode-value="without_deposit">
                        <span class="modal-choice-title">Cl&ocirc;ture sans d&eacute;p&ocirc;t</span>
                        <span class="modal-choice-text">La journ&eacute;e sera valid&eacute;e sans remise bancaire.</span>
                    </button>
                    <button type="button" class="modal-choice-card" data-fin-mode-value="with_deposit" id="fin-journee-mode-with">
                        <span class="modal-choice-title">Cl&ocirc;ture avec d&eacute;p&ocirc;t</span>
                        <span class="modal-choice-text">Saisissez les remises bancaires &agrave; enregistrer.</span>
                    </button>
                </div>
            </div>

            <div id="fin-journee-deposit-section" class="modal-section is-hidden">
                <div class="modal-section-title">D&eacute;p&ocirc;ts bancaires</div>

                <div id="fin-journee-especes-block" class="modal-choice-item is-hidden">
                    <label class="modal-check-row">
                        <input type="checkbox" id="fin-journee-depot-especes">
                        <span>D&eacute;p&ocirc;t d&apos;esp&egrave;ces</span>
                    </label>
                    <div class="modal-inline-grid is-hidden" id="fin-journee-especes-fields">
                        <div class="form-group">
                            <label class="form-label" for="fin-journee-especes-amount">Montant d&eacute;pos&eacute; (&euro;)</label>
                            <input type="number" step="0.01" min="0" id="fin-journee-especes-amount" class="form-input text-right">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fin-journee-especes-remise">N&deg; remise esp&egrave;ces</label>
                            <input type="text" id="fin-journee-especes-remise" class="form-input">
                        </div>
                    </div>
                </div>

                <div id="fin-journee-cheques-block" class="modal-choice-item is-hidden">
                    <label class="modal-check-row">
                        <input type="checkbox" id="fin-journee-depot-cheques">
                        <span>D&eacute;p&ocirc;t de ch&egrave;ques</span>
                    </label>
                    <div class="modal-inline-grid is-hidden" id="fin-journee-cheques-fields">
                        <div class="form-group">
                            <label class="form-label">Montant du d&eacute;p&ocirc;t</label>
                            <div class="modal-static-value" id="fin-journee-cheques-amount"><?= htmlspecialchars(ldcFormatEuro((float) $totaux['cheques']), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="fin-journee-cheques-remise">N&deg; remise ch&egrave;que</label>
                            <input type="text" id="fin-journee-cheques-remise" class="form-input">
                        </div>
                    </div>
                </div>

                <p class="modal-note">S&eacute;lectionnez au moins un d&eacute;p&ocirc;t si vous choisissez la cl&ocirc;ture avec d&eacute;p&ocirc;t.</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="fin-journee-cancel">Annuler</button>
                <button type="button" class="btn btn-primary" id="fin-journee-confirm">Valider la cl&ocirc;ture</button>
            </div>
        </div>
    </div>
</div>

<script>
window.ldcTypeAffaireRules = <?= json_encode(ldcGetTypeAffaireRules(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.ldcTypeEncaissementOptionsByAffaire = <?= json_encode(ldcGetTypeEncaissementOptionsByAffaire(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.ldcAnticipationMonthsWindow = <?= (int) LDC_ANTICIPATION_MONTHS_WINDOW ?>;
window.ldcCaisseConfig = <?= json_encode([
    'fondCaisseDebut' => number_format($fondDebut, 2, '.', ''),
    'fondCaisseFin' => number_format($fondFin, 2, '.', ''),
    'totalEspeces' => number_format((float) $totaux['especes'], 2, '.', ''),
    'totalCheques' => number_format((float) $totaux['cheques'], 2, '.', ''),
    'numRemiseEspeces' => $numRemiseEspeces,
    'numRemiseCheque' => $numRemiseCheque,
    'shouldConfirmOnFirstEntry' => $promptFondCaisseConfirmation,
    'isDayClosed' => $isDayClosed,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($jsUrl . '?v=' . rawurlencode($jsVersion), ENT_QUOTES, 'UTF-8') ?>"></script>
