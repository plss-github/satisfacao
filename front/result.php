<?php

use GlpiPlugin\Satisfacao\Menu;
use GlpiPlugin\Satisfacao\SurveyTicket;

Session::checkRight('config', UPDATE);

Html::header(__('Resultados da pesquisa de satisfação', 'satisfacao'), PLUGIN_SATISFACAO_WEBDIR . '/front/result.php', 'admin', Menu::class);

Menu::renderSubNav('result');

global $DB;

$total    = countElementsInTable(SurveyTicket::getTable());
$answered = countElementsInTable(SurveyTicket::getTable(), ['status' => SurveyTicket::STATUS_ANSWERED]);
$rate     = $total > 0 ? round(($answered / $total) * 100, 1) : 0;

$avg_rating = null;
$iterator = $DB->request([
    'SELECT'     => ['AVG' => 'glpi_plugin_satisfacao_answers.answer AS avg_note'],
    'FROM'       => 'glpi_plugin_satisfacao_answers',
    'INNER JOIN' => [
        'glpi_plugin_satisfacao_questions' => [
            'ON' => [
                'glpi_plugin_satisfacao_answers'   => 'plugin_satisfacao_questions_id',
                'glpi_plugin_satisfacao_questions' => 'id',
            ],
        ],
    ],
    'WHERE' => ['glpi_plugin_satisfacao_questions.type' => 'rating'],
]);
foreach ($iterator as $row) {
    $avg_rating = $row['avg_note'];
}

$indicators = [
    [
        'icon'  => 'ti-send',
        'color' => 'blue',
        'value' => $total,
        'label' => __('Pesquisas geradas', 'satisfacao'),
    ],
    [
        'icon'  => 'ti-checkbox',
        'color' => 'green',
        'value' => $answered,
        'label' => __('Pesquisas respondidas', 'satisfacao'),
    ],
    [
        'icon'  => 'ti-percentage',
        'color' => 'azure',
        'value' => $rate . '%',
        'label' => __('Taxa de resposta', 'satisfacao'),
    ],
    [
        'icon'  => 'ti-star',
        'color' => 'yellow',
        'value' => $avg_rating !== null ? round((float) $avg_rating, 2) : '-',
        'label' => __('Nota média (perguntas do tipo nota)', 'satisfacao'),
    ],
];

echo '<div class="row row-cards mb-4">';
foreach ($indicators as $indicator) {
    echo '<div class="col-sm-6 col-lg-3">';
    echo '<div class="card card-sm">';
    echo '<div class="card-body d-flex align-items-center">';
    echo '<span class="avatar avatar-rounded bg-' . $indicator['color'] . '-lt me-3">'
        . '<i class="ti ' . $indicator['icon'] . ' fs-2"></i></span>';
    echo '<div>';
    echo '<div class="fs-2 fw-bold lh-1">' . htmlescape((string) $indicator['value']) . '</div>';
    echo '<div class="text-muted">' . htmlescape($indicator['label']) . '</div>';
    echo '</div>';
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // col
}
echo '</div>'; // row

echo '<h3 class="mb-2"><i class="ti ti-list-details me-2"></i>' . __('Chamados pesquisados', 'satisfacao') . '</h3>';

Search::show(SurveyTicket::class);

Html::footer();
