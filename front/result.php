<?php

use Glpi\DBAL\QueryExpression;
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

$expired_pending = 0;
$iterator = $DB->request([
    'SELECT'    => ['COUNT' => 'st.id AS cnt'],
    'FROM'      => 'glpi_plugin_satisfacao_surveytickets AS st',
    'LEFT JOIN' => [
        'glpi_plugin_satisfacao_surveysettings AS ss' => [
            'ON' => [
                'ss' => 'entities_id',
                'st' => 'entities_id',
            ],
        ],
    ],
    'WHERE' => [
        'OR' => [
            // Já persistido pelo cron `SurveyTicket::cronSatisfacaoExpire()`.
            'st.status' => SurveyTicket::STATUS_EXPIRED,
            // Vencida mas ainda não processada pelo cron (até 1h de atraso).
            [
                'st.status' => SurveyTicket::STATUS_TO_ANSWER,
                new QueryExpression('COALESCE(ss.validity_days, 0) > 0'),
                new QueryExpression('DATE_ADD(st.date_begin, INTERVAL COALESCE(ss.validity_days, 0) DAY) < NOW()'),
            ],
        ],
    ],
]);
foreach ($iterator as $row) {
    $expired_pending = (int) $row['cnt'];
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
    [
        'icon'  => 'ti-alert-triangle',
        'color' => 'red',
        'value' => $expired_pending,
        'label' => __('Pesquisas expiradas (sem resposta)', 'satisfacao'),
    ],
];

echo '<div class="row row-cards mb-4">';
foreach ($indicators as $indicator) {
    echo '<div class="col-6 col-md-4 col-xl">';
    echo '<div class="card card-sm">';
    echo '<div class="card-body d-flex align-items-center p-2">';
    echo '<span class="avatar avatar-rounded bg-' . $indicator['color'] . '-lt me-2">'
        . '<i class="ti ' . $indicator['icon'] . ' fs-3"></i></span>';
    echo '<div>';
    echo '<div class="fs-3 fw-bold lh-1">' . htmlescape((string) $indicator['value']) . '</div>';
    echo '<div class="text-muted small">' . htmlescape($indicator['label']) . '</div>';
    echo '</div>';
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // col
}
echo '</div>'; // row

echo '<h3 class="mb-2"><i class="ti ti-list-details me-2"></i>' . __('Chamados pesquisados', 'satisfacao') . '</h3>';

Search::show(SurveyTicket::class);

Html::footer();
