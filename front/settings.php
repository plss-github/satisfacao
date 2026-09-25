<?php

use GlpiPlugin\Satisfacao\Menu;
use GlpiPlugin\Satisfacao\SurveySettings;

Session::checkRight('config', UPDATE);

$entities_id = (int) $_SESSION['glpiactive_entity'];

if (isset($_POST['save'])) {
    // Html::select() com 'multiple' sempre manda um campo hidden extra
    // (mesmo nome, sem "[]") com valor "" — garante que o campo exista no
    // POST mesmo com nenhuma opção marcada. Quando nada é marcado, é só
    // essa string vazia que chega; por isso normalizamos pra array aqui.
    $to_id_array = static fn($value): array => is_array($value) ? $value : [];

    SurveySettings::saveForEntity(
        $entities_id,
        $_POST['header_message'] ?? null,
        $_POST['thankyou_message'] ?? null,
        $to_id_array($_POST['excluded_requesttypes'] ?? []),
        $to_id_array($_POST['excluded_categories'] ?? []),
        $to_id_array($_POST['excluded_users'] ?? []),
        $to_id_array($_POST['excluded_groups'] ?? [])
    );
    Session::addMessageAfterRedirect(__('Mensagens salvas.', 'satisfacao'));
    Html::redirect(PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php');
}

Html::header(SurveySettings::getTypeName(2), PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php', 'admin', Menu::class);

Menu::renderSubNav('settings');

$settings = SurveySettings::getForEntity($entities_id);

echo '<div class="alert alert-info d-flex align-items-center mb-3">';
echo '<i class="ti ti-building fs-2 me-2"></i>';
echo sprintf(
    __('Mensagens da entidade atual: %s. Para configurar outra entidade, troque a entidade ativa (seletor no topo da página) e volte aqui.', 'satisfacao'),
    '<strong>' . htmlescape(Dropdown::getDropdownName('glpi_entities', $entities_id)) . '</strong>'
);
echo '</div>';

echo '<form method="post" action="' . PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo '<div class="card mb-4">';
echo '<div class="card-header"><h3 class="card-title"><i class="ti ti-message-2 me-2"></i>'
    . __('Mensagens da pesquisa', 'satisfacao') . '</h3></div>';
echo '<div class="card-body">';
echo '<table class="tab_cadre_fixe">';

echo '<tr class="tab_bg_1">';
echo '<td style="width: 25%">' . __('Mensagem de cabeçalho', 'satisfacao')
    . Menu::helpIcon(__('Aparece no topo da pesquisa, antes das perguntas. Deixe em branco pra não mostrar nada.', 'satisfacao'))
    . '</td>';
echo '<td>';
echo '<textarea name="header_message" rows="3" style="width:100%" placeholder="' . __('Ex: Sua opinião é muito importante para melhorarmos nosso atendimento.', 'satisfacao') . '">'
    . htmlescape((string) ($settings->fields['header_message'] ?? ''))
    . '</textarea>';
echo '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Mensagem de agradecimento', 'satisfacao')
    . Menu::helpIcon(__('Aparece depois que a pessoa responde a pesquisa. Deixe em branco pra usar a mensagem padrão ("Respondida em ...").', 'satisfacao'))
    . '</td>';
echo '<td>';
echo '<textarea name="thankyou_message" rows="3" style="width:100%" placeholder="' . __('Ex: Obrigado por avaliar nosso atendimento!', 'satisfacao') . '">'
    . htmlescape((string) ($settings->fields['thankyou_message'] ?? ''))
    . '</textarea>';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</div>'; // card-body
echo '</div>'; // card

$exclusions = $settings->getExclusions();

echo '<div class="card mb-4">';
echo '<div class="card-header"><h3 class="card-title"><i class="ti ti-filter-off me-2"></i>'
    . __('Não gerar pesquisa quando', 'satisfacao')
    . Menu::helpIcon(__('Chamados que atendam a qualquer um dos critérios abaixo não recebem pesquisa de satisfação. Útil para chamados abertos por ferramentas de monitoramento/integrações. Deixe em branco pra não excluir nada.', 'satisfacao'))
    . '</h3></div>';
echo '<div class="card-body">';
echo '<table class="tab_cadre_fixe">';

echo '<tr class="tab_bg_1">';
echo '<td style="width: 25%">' . __('Origem da requisição', 'satisfacao') . '</td>';
echo '<td>';
RequestType::dropdown([
    'name'     => 'excluded_requesttypes[]',
    'multiple' => true,
    // Multi-select por Dropdown::show(): valores iniciais vão em 'value'
    // (singular), não 'values' — o próprio core sobrescreve 'values' a
    // partir de 'value' no modo multiple.
    'value'    => $exclusions['requesttypes'],
    'width'    => '100%',
]);
echo '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Categoria do chamado', 'satisfacao') . '</td>';
echo '<td>';
ITILCategory::dropdown([
    'name'     => 'excluded_categories[]',
    'multiple' => true,
    'value'    => $exclusions['categories'],
    'width'    => '100%',
    'entity'   => $entities_id,
]);
echo '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Solicitante', 'satisfacao')
    . Menu::helpIcon(__('Ex: conta técnica usada por uma ferramenta de monitoramento pra abrir chamados automaticamente.', 'satisfacao'))
    . '</td>';
echo '<td>';
User::dropdown([
    'name'     => 'excluded_users[]',
    'multiple' => true,
    'value'    => $exclusions['users'],
    'width'    => '100%',
    'right'    => 'all',
]);
echo '</td>';
echo '</tr>';

echo '<tr class="tab_bg_1">';
echo '<td>' . __('Grupo do solicitante', 'satisfacao') . '</td>';
echo '<td>';
Group::dropdown([
    'name'     => 'excluded_groups[]',
    'multiple' => true,
    'value'    => $exclusions['groups'],
    'width'    => '100%',
]);
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</div>'; // card-body
echo '</div>'; // card

echo '<div class="mb-3">';
echo Html::submit(__('Salvar', 'satisfacao'), ['name' => 'save']);
echo '</div>';
echo '</form>';

Html::footer();
