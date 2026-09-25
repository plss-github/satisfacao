<?php

use GlpiPlugin\Satisfacao\SurveySettings;

Session::checkRight('config', UPDATE);

$entities_id = (int) $_SESSION['glpiactive_entity'];

if (isset($_POST['save'])) {
    SurveySettings::saveForEntity(
        $entities_id,
        $_POST['header_message'] ?? null,
        $_POST['thankyou_message'] ?? null
    );
    Session::addMessageAfterRedirect(__('Mensagens salvas.', 'satisfacao'));
    Html::redirect(PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php');
}

Html::header(SurveySettings::getTypeName(2), PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php', 'admin', \GlpiPlugin\Satisfacao\Menu::class);

echo '<div class="mb-2">';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/question.php">'
    . __('Gerenciar perguntas', 'satisfacao') . '</a>';
echo ' | ';
echo '<a href="' . PLUGIN_SATISFACAO_WEBDIR . '/front/result.php">'
    . __('Ver resultados da pesquisa', 'satisfacao') . '</a>';
echo '</div>';

$settings = SurveySettings::getForEntity($entities_id);

echo '<div class="alert alert-info">' . sprintf(
    __('Mensagens da entidade atual: %s. Para configurar outra entidade, troque a entidade ativa (seletor no topo da página) e volte aqui.', 'satisfacao'),
    '<strong>' . htmlescape(Dropdown::getDropdownName('glpi_entities', $entities_id)) . '</strong>'
) . '</div>';

echo '<form method="post" action="' . PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo '<table class="tab_cadre_fixe">';

echo '<tr class="tab_bg_1">';
echo '<td style="width: 25%">' . __('Mensagem de cabeçalho', 'satisfacao') . '</td>';
echo '<td>';
echo '<textarea name="header_message" rows="3" style="width:100%" placeholder="' . __('Ex: Sua opinião é muito importante para melhorarmos nosso atendimento.', 'satisfacao') . '">'
    . htmlescape((string) ($settings->fields['header_message'] ?? ''))
    . '</textarea>';
echo '<div class="text-muted mt-1">' . __('Aparece no topo da pesquisa, antes das perguntas. Deixe em branco pra não mostrar nada.', 'satisfacao') . '</div>';
echo '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Mensagem de agradecimento', 'satisfacao') . '</td>';
echo '<td>';
echo '<textarea name="thankyou_message" rows="3" style="width:100%" placeholder="' . __('Ex: Obrigado por avaliar nosso atendimento!', 'satisfacao') . '">'
    . htmlescape((string) ($settings->fields['thankyou_message'] ?? ''))
    . '</textarea>';
echo '<div class="text-muted mt-1">' . __('Aparece depois que a pessoa responde a pesquisa. Deixe em branco pra usar a mensagem padrão ("Respondida em ...").', 'satisfacao') . '</div>';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '<div class="mt-2">';
echo Html::submit(__('Salvar', 'satisfacao'), ['name' => 'save']);
echo '</div>';
echo '</form>';

Html::footer();
