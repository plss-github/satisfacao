<?php

use GlpiPlugin\Satisfacao\SurveyTicket;

Session::checkRight('config', UPDATE);

Html::header(__('Resultados da pesquisa de satisfação', 'satisfacao'), PLUGIN_SATISFACAO_WEBDIR . '/front/result.php', 'admin', \GlpiPlugin\Satisfacao\Menu::class);

echo '<div class="mb-2">';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/question.php">'
    . __('Gerenciar perguntas', 'satisfacao') . '</a>';
echo ' | ';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php">'
    . __('Mensagens de cabeçalho e agradecimento', 'satisfacao') . '</a>';
echo '</div>';

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

echo '<table class="tab_cadre_fixe">';
echo '<tr><th colspan="2">' . __('Indicadores', 'satisfacao') . '</th></tr>';
echo '<tr class="tab_bg_1"><td>' . __('Pesquisas geradas', 'satisfacao') . '</td><td>' . $total . '</td></tr>';
echo '<tr class="tab_bg_1"><td>' . __('Pesquisas respondidas', 'satisfacao') . '</td><td>' . $answered . '</td></tr>';
echo '<tr class="tab_bg_1"><td>' . __('Taxa de resposta', 'satisfacao') . '</td><td>' . $rate . '%</td></tr>';
echo '<tr class="tab_bg_1"><td>' . __('Nota média (perguntas do tipo nota)', 'satisfacao') . '</td><td>'
    . ($avg_rating !== null ? round((float) $avg_rating, 2) : '-')
    . '</td></tr>';
echo '</table>';

echo '<br>';

Search::show(SurveyTicket::class);

Html::footer();
