<?php

use GlpiPlugin\Satisfacao\Question;

Session::checkRight('config', UPDATE);

Html::header(Question::getTypeName(2), PLUGIN_SATISFACAO_WEBDIR . '/front/question.php', 'admin', \GlpiPlugin\Satisfacao\Menu::class);

echo '<div class="alert alert-info">' . __(
    'Não existe um objeto "pesquisa" separado: todas as perguntas ativas cadastradas para a entidade de um chamado formam a pesquisa que aparece quando esse chamado é fechado. Para montar a pesquisa, basta cadastrar as perguntas abaixo.',
    'satisfacao'
) . '</div>';

echo '<div class="mb-2 d-flex justify-content-between align-items-center">';
echo '<a class="btn btn-primary" href="' . Question::getFormURL() . '">'
    . '<i class="ti ti-plus"></i> ' . __('Nova pergunta', 'satisfacao') . '</a>';
echo '<div>';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php">'
    . __('Mensagens de cabeçalho e agradecimento', 'satisfacao') . '</a>';
echo ' | ';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/result.php">'
    . __('Ver resultados da pesquisa', 'satisfacao') . '</a>';
echo '</div>';
echo '</div>';

Search::show(Question::class);

Html::footer();
