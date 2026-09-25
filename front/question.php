<?php

use GlpiPlugin\Satisfacao\Menu;
use GlpiPlugin\Satisfacao\Question;

Session::checkRight('config', UPDATE);

Html::header(Question::getTypeName(2), PLUGIN_SATISFACAO_WEBDIR . '/front/question.php', 'admin', Menu::class);

Menu::renderSubNav('question');

echo '<div class="alert alert-info d-flex align-items-center">';
echo '<i class="ti ti-info-circle fs-2 me-2"></i>';
echo __(
    'Não existe um objeto "pesquisa" separado: todas as perguntas ativas cadastradas para a entidade de um chamado formam a pesquisa que aparece quando esse chamado é fechado. Para montar a pesquisa, basta cadastrar as perguntas abaixo.',
    'satisfacao'
);
echo '</div>';

echo '<div class="mb-3">';
echo '<a class="btn btn-primary" href="' . Question::getFormURL() . '">'
    . '<i class="ti ti-plus me-1"></i>' . __('Nova pergunta', 'satisfacao') . '</a>';
echo '</div>';

Search::show(Question::class);

Html::footer();
