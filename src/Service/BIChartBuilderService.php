<?php

namespace App\Service;

class BIChartBuilderService
{
    public function getWidgetCatalog(): array
    {
        return [
            ['type' => 'kpi', 'label' => 'Indicateur KPI', 'icon' => 'bi-speedometer2', 'defaultTitle' => 'KPI principal'],
            ['type' => 'counter', 'label' => 'Compteur', 'icon' => 'bi-123', 'defaultTitle' => 'Compteur global'],
            ['type' => 'percentage', 'label' => 'Carte de pourcentage', 'icon' => 'bi-percent', 'defaultTitle' => 'Part en pourcentage'],
            ['type' => 'bar', 'label' => 'Graphique en barres', 'icon' => 'bi-bar-chart-line', 'defaultTitle' => 'Repartition par categorie'],
            ['type' => 'line', 'label' => 'Courbe d evolution', 'icon' => 'bi-graph-up', 'defaultTitle' => 'Evolution dans le temps'],
            ['type' => 'pie', 'label' => 'Graphique en secteurs', 'icon' => 'bi-pie-chart', 'defaultTitle' => 'Part par categorie'],
            ['type' => 'doughnut', 'label' => 'Camembert annulaire', 'icon' => 'bi-circle', 'defaultTitle' => 'Distribution annulaire'],
            ['type' => 'histogram', 'label' => 'Histogramme', 'icon' => 'bi-distribute-vertical', 'defaultTitle' => 'Distribution numerique'],
            ['type' => 'distribution-table', 'label' => 'Tableau de repartition', 'icon' => 'bi-list-columns-reverse', 'defaultTitle' => 'Repartition detaillee'],
            ['type' => 'table', 'label' => 'Tableau de donnees', 'icon' => 'bi-table', 'defaultTitle' => 'Tableau detaille'],
        ];
    }

    public function getBuilderOptions(array $datasetPayload): array
    {
        $columns = is_array($datasetPayload['columns'] ?? null) ? $datasetPayload['columns'] : [];
        $columnOptions = [];
        $numericColumns = [];
        $dimensionColumns = [];
        $dateColumns = [];

        foreach ($columns as $column) {
            $key = trim((string) ($column['key'] ?? ''));
            $label = trim((string) ($column['label'] ?? $key));
            $type = trim((string) ($column['type'] ?? 'string'));
            if ($key === '') {
                continue;
            }

            $option = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'type' => $type,
            ];

            $columnOptions[] = $option;

            if ($type === 'number') {
                $numericColumns[] = $option;
            }

            if (in_array($type, ['string', 'boolean'], true)) {
                $dimensionColumns[] = $option;
            }

            if ($type === 'date') {
                $dateColumns[] = $option;
            }
        }

        return [
            'widgets' => $this->getWidgetCatalog(),
            'aggregations' => [
                ['key' => 'count', 'label' => 'Nombre de lignes'],
                ['key' => 'sum', 'label' => 'Total'],
                ['key' => 'avg', 'label' => 'Moyenne'],
                ['key' => 'percentage', 'label' => 'Pourcentage'],
            ],
            'layouts' => [
                ['key' => '1/8', 'label' => '1/8'],
                ['key' => '2/8', 'label' => '2/8'],
                ['key' => '3/8', 'label' => '3/8'],
                ['key' => '4/8', 'label' => '4/8'],
                ['key' => '5/8', 'label' => '5/8'],
                ['key' => '6/8', 'label' => '6/8'],
                ['key' => '7/8', 'label' => '7/8'],
                ['key' => '8/8', 'label' => '8/8'],
            ],
            'columns' => $columnOptions,
            'numericColumns' => $numericColumns,
            'dimensionColumns' => $dimensionColumns,
            'dateColumns' => $dateColumns,
        ];
    }

    public function buildSuggestedWidgets(array $datasetPayload, array $preferredColumns = []): array
    {
        $numeric = trim((string) ($preferredColumns['numeric'] ?? ''));
        $category = trim((string) ($preferredColumns['category'] ?? ''));
        $date = trim((string) ($preferredColumns['date'] ?? ''));

        $suggestions = [];

        if ($numeric !== '') {
            $suggestions[] = [
                'type' => 'kpi',
                'title' => 'Valeur totale',
                'valueColumn' => $numeric,
                'aggregation' => 'sum',
                'layout' => '1/3',
                'displayMode' => 'value',
            ];
        }

        if ($numeric !== '' && $category !== '') {
            $suggestions[] = [
                'type' => 'bar',
                'title' => 'Repartition par categorie',
                'valueColumn' => $numeric,
                'dimensionColumn' => $category,
                'aggregation' => 'sum',
                'layout' => '1/2',
                'displayMode' => 'chart',
            ];

            $suggestions[] = [
                'type' => 'pie',
                'title' => 'Part par categorie',
                'valueColumn' => $numeric,
                'dimensionColumn' => $category,
                'aggregation' => 'sum',
                'layout' => '1/2',
                'displayMode' => 'chart',
            ];
        }

        if ($numeric !== '' && $date !== '') {
            $suggestions[] = [
                'type' => 'line',
                'title' => 'Evolution dans le temps',
                'valueColumn' => $numeric,
                'dimensionColumn' => $date,
                'aggregation' => 'sum',
                'layout' => 'full',
                'displayMode' => 'chart',
            ];
        }

        return $suggestions;
    }
}
