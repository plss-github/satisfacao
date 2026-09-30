<?php

namespace GlpiPlugin\Satisfacao;

use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use Dropdown;
use Entity;
use Html;
use NotificationEvent;
use Session;
use Ticket;

/**
 * Liga uma pesquisa de satisfação a um chamado e fornece a aba exibida na
 * tela do chamado (formulário de resposta ou visualização somente leitura).
 */
class SurveyTicket extends CommonDBTM
{
    public static $rightname = 'config';

    public const STATUS_TO_ANSWER = 1;
    public const STATUS_ANSWERED  = 2;
    public const STATUS_EXPIRED   = 3;

    public static function getTypeName($nb = 0)
    {
        return _n('Pesquisa de satisfação', 'Pesquisas de satisfação', $nb, 'satisfacao');
    }

    /**
     * Cria a pesquisa para um chamado recém-fechado, se ainda não existir
     * uma e a entidade do chamado tiver ao menos uma pergunta ativa.
     * Garante a regra de "uma pesquisa por chamado" (reabrir e fechar de
     * novo não gera outra).
     *
     * @param Ticket $ticket
     *
     * @return void
     */
    public static function createForTicket(Ticket $ticket): void
    {
        $tickets_id = (int) $ticket->getID();

        if (countElementsInTable(self::getTable(), ['tickets_id' => $tickets_id]) > 0) {
            return;
        }

        $has_active_question = countElementsInTable(Question::getTable(), [
            'is_active'   => 1,
            'entities_id' => $ticket->fields['entities_id'],
        ]) > 0;

        if (!$has_active_question) {
            return;
        }

        if (SurveySettings::ticketIsExcluded($ticket)) {
            return;
        }

        $survey = new self();
        $id = $survey->add([
            'tickets_id'  => $tickets_id,
            'entities_id' => $ticket->fields['entities_id'],
            'date_begin'  => date('Y-m-d H:i:s'),
            'status'      => self::STATUS_TO_ANSWER,
        ]);

        if ($id) {
            // Evento próprio (não o "satisfaction" nativo, pra não
            // duplicar/confundir se a pesquisa nativa também estiver
            // ativa na mesma entidade — ver README).
            NotificationEvent::raiseEvent('satisfacao_survey', $ticket);
        }
    }

    /**
     * Data/hora limite pra responder a pesquisa (fechamento do chamado +
     * prazo de validade configurado na entidade), ou `null` se a entidade
     * não tem prazo configurado (0 = sem expiração).
     *
     * @return string|null Formato `Y-m-d H:i:s`.
     */
    public function getExpirationDate(): ?string
    {
        if (empty($this->fields['date_begin'])) {
            return null;
        }

        $validity_days = SurveySettings::getForEntity((int) $this->fields['entities_id'])->getValidityDays();
        if ($validity_days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($this->fields['date_begin']) + ($validity_days * 86400));
    }

    /**
     * Indica se o prazo pra responder a pesquisa já venceu. Uma pesquisa
     * já respondida nunca é considerada expirada.
     *
     * @return boolean
     */
    public function isExpired(): bool
    {
        $status = (int) $this->fields['status'];

        if ($status === self::STATUS_ANSWERED) {
            return false;
        }

        if ($status === self::STATUS_EXPIRED) {
            return true;
        }

        $expiration = $this->getExpirationDate();

        return $expiration !== null && strtotime($expiration) < time();
    }

    /**
     * Descrição da ação automática exibida em Configurar > Ações
     * automáticas.
     *
     * @param string $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'satisfacaoexpire':
                return [
                    'description' => __('Marca como expiradas as pesquisas de satisfação vencidas', 'satisfacao'),
                ];
        }
        return [];
    }

    /**
     * Ação automática: persiste `status = STATUS_EXPIRED` nas pesquisas
     * pendentes cujo prazo de validade (configurado por entidade em
     * `SurveySettings`) já venceu. `isExpired()`/`showSurvey()`/
     * `ajax/answer.php` já bloqueiam a resposta em tempo real assim que o
     * prazo vence (sem depender do cron); esta ação serve pra manter a
     * coluna "Situação" persistida e filtrável na busca/relatórios (ver
     * `getSpecificValueToSelect()`), já que o motor de busca do GLPI
     * filtra pelo valor gravado no banco, não por um cálculo dinâmico.
     *
     * @param \CronTask|null $task
     *
     * @return integer 0 ou 1 (padrão de retorno de ação automática do GLPI)
     */
    public static function cronSatisfacaoExpire($task = null): int
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => ['st.id'],
            'FROM'      => self::getTable() . ' AS st',
            'LEFT JOIN' => [
                SurveySettings::getTable() . ' AS ss' => [
                    'ON' => [
                        'ss' => 'entities_id',
                        'st' => 'entities_id',
                    ],
                ],
            ],
            'WHERE' => [
                'st.status' => self::STATUS_TO_ANSWER,
                new \Glpi\DBAL\QueryExpression('COALESCE(ss.validity_days, 0) > 0'),
                new \Glpi\DBAL\QueryExpression(
                    'DATE_ADD(st.date_begin, INTERVAL COALESCE(ss.validity_days, 0) DAY) < NOW()'
                ),
            ],
        ]);

        $count = 0;
        foreach ($iterator as $row) {
            $survey = new self();
            if ($survey->update(['id' => $row['id'], 'status' => self::STATUS_EXPIRED])) {
                $count++;
            }
        }

        if ($task !== null) {
            $task->addVolume($count);
        }

        return $count > 0 ? 1 : 0;
    }

    /**
     * Busca a pesquisa (se existir) associada a um chamado.
     *
     * @param integer $tickets_id
     *
     * @return self|null
     */
    public static function getForTicket(int $tickets_id): ?self
    {
        $survey = new self();
        if ($survey->getFromDBByCrit(['tickets_id' => $tickets_id])) {
            return $survey;
        }
        return null;
    }

    /**
     * Registra o tipo "pesquisa de satisfação" pro mecanismo de timeline
     * do GLPI (hook `timeline_answer_actions`). Diferente dos demais
     * hooks, este é chamado via `is_callable()` direto pelo core, sem
     * garantir antes que `hook.php` do plugin foi incluído — por isso a
     * lógica fica aqui na classe (autoload PSR-4), não em hook.php.
     * `hide_in_menu` fica true porque a pesquisa nunca é criada
     * manualmente pelo usuário através do "+" da timeline — só
     * automaticamente quando o chamado fecha. O registro serve apenas
     * pra mapear o `type` usado em `timeline_items` ao template Twig que
     * sabe renderizar a pesquisa.
     *
     * @param array $params ['item' => CommonITILObject]
     *
     * @return array
     */
    public static function getTimelineAnswerActions(array $params): array
    {
        if (!($params['item'] instanceof Ticket)) {
            return [];
        }

        return [
            'satisfacao_survey' => [
                'type'         => self::class,
                'class'        => self::class,
                'icon'         => 'ti ti-star',
                'label'        => __('Pesquisa de satisfação', 'satisfacao'),
                'short_label'  => __('Pesquisa', 'satisfacao'),
                'template'     => '@satisfacao/timeline_survey.html.twig',
                'item'         => new self(),
                'hide_in_menu' => true,
            ],
        ];
    }

    /**
     * Ponto de entrada chamado pelo template Twig do item de timeline
     * (templates/timeline_survey.html.twig, via `timeline_answer_actions`
     * + `timeline_items`). Reaproveita o mesmo `showSurvey()` usado na aba.
     *
     * @param integer $survey_id
     * @param Ticket  $ticket
     *
     * @return void
     */
    public static function renderTimelineEntry(int $survey_id, Ticket $ticket): void
    {
        $survey = new self();
        if ($survey->getFromDB($survey_id)) {
            $survey->showSurvey($ticket);
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Ticket) || !$item->getID()) {
            return '';
        }

        $survey = self::getForTicket((int) $item->getID());
        if ($survey === null) {
            return '';
        }

        return self::createTabEntry(__('Pesquisa de satisfação', 'satisfacao'), 0, null, 'ti ti-star');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Ticket)) {
            return false;
        }

        $survey = self::getForTicket((int) $item->getID());
        if ($survey === null) {
            return true;
        }

        $survey->showSurvey($item);

        return true;
    }

    /**
     * Renderiza o formulário de resposta (editável para o solicitante
     * enquanto pendente, somente leitura depois de respondida).
     *
     * @param Ticket $ticket
     *
     * @return void
     */
    public function showSurvey(Ticket $ticket): void
    {
        $answered     = (int) $this->fields['status'] === self::STATUS_ANSWERED;
        $is_requester = $ticket->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID());
        $expired      = $this->isExpired();
        $editable     = !$answered && $is_requester && !$expired;

        $questions = (new Question())->find(
            ['entities_id' => $this->fields['entities_id'], 'is_active' => 1],
            'rank ASC'
        );

        if (empty($questions)) {
            echo '<div class="alert alert-info">' . __('Nenhuma pergunta configurada.', 'satisfacao') . '</div>';
            return;
        }

        if (!$answered && $expired) {
            echo '<div class="alert alert-warning">'
                . sprintf(
                    __('Esta pesquisa expirou em %s e não pode mais ser respondida.', 'satisfacao'),
                    Html::convDateTime($this->getExpirationDate())
                )
                . '</div>';
            return;
        }

        if (!$answered && !$is_requester) {
            echo '<div class="alert alert-info">'
                . __('Aguardando resposta do solicitante do chamado.', 'satisfacao')
                . '</div>';
            return;
        }

        $settings = SurveySettings::getForEntity((int) $this->fields['entities_id']);

        if ($editable && !empty($settings->fields['header_message'])) {
            echo '<div class="alert alert-info">' . nl2br(htmlescape($settings->fields['header_message'])) . '</div>';
        }

        if ($editable) {
            $expiration = $this->getExpirationDate();
            if ($expiration !== null) {
                echo '<div class="text-muted mb-2">'
                    . sprintf(__('Disponível para resposta até %s.', 'satisfacao'), Html::convDateTime($expiration))
                    . '</div>';
            }
        }

        $existing_answers = [];
        if ($answered) {
            foreach ((new Answer())->find(['plugin_satisfacao_tickets_id' => $this->getID()]) as $row) {
                $existing_answers[(int) $row['plugin_satisfacao_questions_id']] = $row['answer'];
            }
        }

        if ($editable) {
            echo '<form method="post" action="' . PLUGIN_SATISFACAO_WEBDIR . '/ajax/answer.php">';
            echo Html::hidden('tickets_id', ['value' => $ticket->getID()]);
            echo Html::hidden('id', ['value' => $this->getID()]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        }

        echo '<table class="tab_cadre_fixe">';
        foreach ($questions as $question) {
            $qid   = (int) $question['id'];
            $value = $existing_answers[$qid] ?? null;

            echo '<tr class="tab_bg_1">';
            echo '<td style="width: 30%">' . htmlescape($question['name']);
            if ($question['is_mandatory']) {
                echo ' <span class="text-danger">*</span>';
            }
            echo '</td>';
            echo '<td>';
            $this->renderField($question, $qid, $value, $editable);
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';

        if ($editable) {
            echo '<div class="mt-2">';
            echo Html::submit(_x('button', 'Enviar', 'satisfacao'), ['name' => 'submit_answer']);
            echo '</div>';
            echo '</form>';
        } elseif ($answered) {
            if (!empty($settings->fields['thankyou_message'])) {
                echo '<div class="alert alert-success mt-2">'
                    . nl2br(htmlescape($settings->fields['thankyou_message']))
                    . '</div>';
            }
            echo '<div class="text-muted mt-2">';
            printf(
                __('Respondida em %s', 'satisfacao'),
                Html::convDateTime($this->fields['date_answered'])
            );
            echo '</div>';
        }
    }

    /**
     * Renderiza o input correspondente ao tipo de uma pergunta.
     *
     * @param array   $question Linha da tabela de perguntas.
     * @param integer $qid
     * @param mixed   $value    Valor já salvo, se houver.
     * @param boolean $editable
     *
     * @return void
     */
    private function renderField(array $question, int $qid, $value, bool $editable): void
    {
        $name     = 'answer_' . $qid;
        $type     = $question['type'];
        $options  = !empty($question['options']) ? (json_decode($question['options'], true) ?: []) : [];
        $disabled = $editable ? '' : 'disabled';

        $other_label      = $question['other_option_label'] ?? null;
        $has_other_option = !empty($other_label) && in_array($other_label, $options, true);

        switch ($type) {
            case Question::TYPE_RATING:
                for ($i = 1; $i <= 5; $i++) {
                    $checked = ((string) $value === (string) $i) ? 'checked' : '';
                    echo '<label style="margin-right:8px">'
                        . "<input type=\"radio\" name=\"{$name}\" value=\"{$i}\" {$checked} {$disabled}> {$i}"
                        . '</label>';
                }
                break;

            case Question::TYPE_TEXT:
                echo "<textarea name=\"{$name}\" rows=\"2\" style=\"width:100%\" {$disabled}>"
                    . htmlescape((string) $value)
                    . '</textarea>';
                break;

            case Question::TYPE_CHECKBOX:
                $decoded  = self::decodeChoiceAnswer($value);
                $selected = is_array($decoded['selected']) ? $decoded['selected'] : [];
                $other_id = 'satisfacao_other_' . $qid;

                foreach ($options as $option) {
                    $checked     = in_array($option, $selected, true) ? 'checked' : '';
                    $safe_option = htmlescape($option);
                    $is_other    = $has_other_option && $option === $other_label;
                    $onchange    = ($is_other && $editable)
                        ? " onchange=\"document.getElementById('{$other_id}').style.display=this.checked?'block':'none';\""
                        : '';
                    echo '<label style="display:block">'
                        . "<input type=\"checkbox\" name=\"{$name}[]\" value=\"{$safe_option}\" {$checked} {$disabled}{$onchange}> {$safe_option}"
                        . '</label>';

                    if ($is_other) {
                        // Mostra a caixa de comentário se a opção "outros" está
                        // marcada — vale tanto editável (resposta já marcada
                        // antes de recarregar) quanto somente-leitura (resposta
                        // salva). Sem depender de $editable: antes disso, a
                        // caixa aparecia sempre no modo somente-leitura, mesmo
                        // quando "outros" nunca foi marcado.
                        $show = $checked ? 'block' : 'none';
                        echo "<div id=\"{$other_id}\" style=\"display:{$show}; margin: 2px 0 8px 24px;\">";
                        echo "<textarea name=\"{$name}_other\" rows=\"2\" style=\"width:100%\" placeholder=\""
                            . __('Comente aqui', 'satisfacao') . "\" {$disabled}>"
                            . htmlescape((string) $decoded['other'])
                            . '</textarea>';
                        echo '</div>';
                    }
                }
                break;

            case Question::TYPE_DROPDOWN:
                $decoded        = self::decodeChoiceAnswer($value);
                $selected_value = (string) $decoded['selected'];
                $other_id       = 'satisfacao_other_' . $qid;
                $onchange = '';
                if ($has_other_option && $editable) {
                    // json_encode() envolve strings em aspas duplas — como o
                    // atributo HTML também usa aspas duplas, embutir o valor
                    // cru quebrava o atributo no meio (o resto virava lixo,
                    // o onchange nunca era interpretado pelo navegador).
                    // htmlescape() converte as aspas em &quot;, que o HTML
                    // decodifica de volta corretamente antes de rodar o JS.
                    $other_label_js = json_encode($other_label, JSON_UNESCAPED_UNICODE);
                    $onchange = ' onchange="' . htmlescape(
                        "document.getElementById('{$other_id}').style.display=(this.value==={$other_label_js})?'block':'none';"
                    ) . '"';
                }

                echo "<select name=\"{$name}\" {$disabled}{$onchange}>";
                echo '<option value="">-- ' . __('Selecione', 'satisfacao') . ' --</option>';
                foreach ($options as $option) {
                    $sel         = ($selected_value === (string) $option) ? 'selected' : '';
                    $safe_option = htmlescape($option);
                    echo "<option value=\"{$safe_option}\" {$sel}>{$safe_option}</option>";
                }
                echo '</select>';

                if ($has_other_option) {
                    // Mesmo raciocínio do checkbox acima: mostra só quando a
                    // opção "outros" está de fato selecionada, em qualquer modo.
                    $show = ($selected_value === $other_label) ? 'block' : 'none';
                    echo "<div id=\"{$other_id}\" style=\"display:{$show}; margin-top:4px;\">";
                    echo "<textarea name=\"{$name}_other\" rows=\"2\" style=\"width:100%\" placeholder=\""
                        . __('Comente aqui', 'satisfacao') . "\" {$disabled}>"
                        . htmlescape((string) $decoded['other'])
                        . '</textarea>';
                    echo '</div>';
                }
                break;
        }
    }

    /**
     * Decodifica uma resposta de checkbox/lista, que pode estar no
     * formato "plano" antigo (array JSON simples pra checkbox, string
     * simples pra lista) ou no formato novo `{"selected": ..., "other": ...}`
     * usado quando a pergunta tem uma opção com comentário livre.
     *
     * @param mixed $value
     *
     * @return array{selected: mixed, other: ?string}
     */
    private static function decodeChoiceAnswer($value): array
    {
        if (is_array($value)) {
            return ['selected' => $value, 'other' => null];
        }

        $decoded = json_decode((string) $value, true);
        if (is_array($decoded) && array_key_exists('selected', $decoded)) {
            return ['selected' => $decoded['selected'], 'other' => $decoded['other'] ?? null];
        }

        // Formato antigo: array simples (checkbox) ou string simples (lista).
        return ['selected' => is_array($decoded) ? $decoded : (string) $value, 'other' => null];
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(),
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => Ticket::getTable(),
            'field'         => 'name',
            'name'          => Ticket::getTypeName(1),
            'datatype'      => 'itemlink',
            'itemlink_type' => Ticket::class,
            'massiveaction' => false,
            'linkfield'     => 'tickets_id',
        ];

        $tab[] = [
            'id'       => '2',
            'table'    => self::getTable(),
            'field'    => 'date_begin',
            'name'     => __('Data de fechamento do chamado', 'satisfacao'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'       => '3',
            'table'    => self::getTable(),
            'field'    => 'date_answered',
            'name'     => __('Data de resposta', 'satisfacao'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'               => '4',
            'table'            => self::getTable(),
            'field'            => 'status',
            'name'             => __('Situação', 'satisfacao'),
            'datatype'         => 'specific',
            'searchtype'       => 'equals',
            // Necessário em getSpecificValueToDisplay() pra saber se uma
            // pesquisa pendente já passou do prazo de validade da entidade.
            'additionalfields' => ['date_begin', 'entities_id'],
        ];

        $tab[] = [
            'id'        => '5',
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('Respondido por', 'satisfacao'),
            'datatype'  => 'itemlink',
            'right'     => 'all',
            'linkfield' => 'users_id_answered',
        ];

        // Coluna "Entidade" — sem isso o motor de busca do GLPI não
        // oferece a entidade em "Selecionar itens padrão a exibir".
        $tab[] = [
            'id'       => '80',
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => Entity::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if ($field === 'status') {
            $status = (int) $values['status'];

            // Cobre tanto o status já persistido pelo cron
            // (`cronSatisfacaoExpire()`) quanto o intervalo entre o
            // vencimento e a próxima execução do cron (até 1h de atraso),
            // calculado na hora a partir de date_begin/entities_id.
            if ($status === self::STATUS_EXPIRED || ($status === self::STATUS_TO_ANSWER && self::isExpiredByValues($values))) {
                return __('Expirada', 'satisfacao');
            }

            $labels = [
                self::STATUS_TO_ANSWER => __('Aguardando resposta', 'satisfacao'),
                self::STATUS_ANSWERED  => __('Respondida', 'satisfacao'),
                self::STATUS_EXPIRED   => __('Expirada', 'satisfacao'),
            ];
            return $labels[$status] ?? $values['status'];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Input do critério de busca/ação em massa pra "Situação" — sem isso,
     * mesmo com `datatype => specific`, o motor de busca cai no input de
     * texto livre padrão (e digitar "Aguardando resposta"/"Respondida"
     * nunca bate com o valor numérico gravado no banco).
     *
     * @param string       $field
     * @param string       $name
     * @param string|array $values
     * @param array        $options
     *
     * @return string
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if ($field === 'status') {
            if (!is_array($values)) {
                $values = [$field => $values];
            }

            $options['name']               = $name;
            $options['value']              = $values[$field] ?? '';
            $options['display']            = false;
            $options['display_emptychoice'] = true;

            $labels = [
                self::STATUS_TO_ANSWER => __('Aguardando resposta', 'satisfacao'),
                self::STATUS_ANSWERED  => __('Respondida', 'satisfacao'),
                self::STATUS_EXPIRED   => __('Expirada', 'satisfacao'),
            ];

            return Dropdown::showFromArray($name, $labels, $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Verifica expiração a partir de uma linha de resultado de busca
     * (contexto estático de `getSpecificValueToDisplay()`, sem instância
     * carregada) — usa `date_begin`/`entities_id` trazidos via
     * `additionalfields` na coluna "Situação" de `rawSearchOptions()`.
     *
     * @param array $values
     *
     * @return boolean
     */
    private static function isExpiredByValues(array $values): bool
    {
        if (empty($values['date_begin']) || !isset($values['entities_id'])) {
            return false;
        }

        $validity_days = SurveySettings::getForEntity((int) $values['entities_id'])->getValidityDays();
        if ($validity_days <= 0) {
            return false;
        }

        return (strtotime($values['date_begin']) + ($validity_days * 86400)) < time();
    }
}
