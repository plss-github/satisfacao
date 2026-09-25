<?php

use GlpiPlugin\Satisfacao\SurveyTicket;

/**
 * Cria as tabelas do plugin na instalação.
 *
 * @return boolean
 */
function plugin_satisfacao_install()
{
    global $DB;

    $charset = 'utf8mb4';
    $collate = 'utf8mb4_unicode_ci';

    if (!$DB->tableExists('glpi_plugin_satisfacao_questions')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_satisfacao_questions` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT 0,
                `name` varchar(255) NOT NULL DEFAULT '',
                `type` varchar(20) NOT NULL DEFAULT 'text',
                `options` text DEFAULT NULL,
                `other_option_label` varchar(255) DEFAULT NULL,
                `is_mandatory` tinyint NOT NULL DEFAULT 0,
                `is_active` tinyint NOT NULL DEFAULT 1,
                `rank` int NOT NULL DEFAULT 0,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entities_id` (`entities_id`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};"
        ) or die($DB->error());
    } elseif (!$DB->fieldExists('glpi_plugin_satisfacao_questions', 'other_option_label')) {
        // Instalação existente de antes da opção "outros, comente" — adiciona a coluna.
        $DB->doQuery(
            "ALTER TABLE `glpi_plugin_satisfacao_questions`
                ADD COLUMN `other_option_label` varchar(255) DEFAULT NULL AFTER `options`;"
        ) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_satisfacao_surveytickets')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_satisfacao_surveytickets` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `tickets_id` int unsigned NOT NULL,
                `entities_id` int unsigned NOT NULL DEFAULT 0,
                `date_begin` timestamp NULL DEFAULT NULL,
                `date_answered` timestamp NULL DEFAULT NULL,
                `users_id_answered` int unsigned NOT NULL DEFAULT 0,
                `status` tinyint NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `tickets_id` (`tickets_id`),
                KEY `entities_id` (`entities_id`),
                KEY `status` (`status`),
                KEY `users_id_answered` (`users_id_answered`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};"
        ) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_satisfacao_answers')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_satisfacao_answers` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_satisfacao_tickets_id` int unsigned NOT NULL,
                `plugin_satisfacao_questions_id` int unsigned NOT NULL,
                `answer` text DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `plugin_satisfacao_tickets_id` (`plugin_satisfacao_tickets_id`),
                KEY `plugin_satisfacao_questions_id` (`plugin_satisfacao_questions_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};"
        ) or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_satisfacao_surveysettings')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_satisfacao_surveysettings` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT 0,
                `header_message` text DEFAULT NULL,
                `thankyou_message` text DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collate};"
        ) or die($DB->error());
    }

    plugin_satisfacao_install_notification();
    plugin_satisfacao_install_view();

    return true;
}

/**
 * Cria (ou substitui) a view `glpi_plugin_satisfacao_vw_answers`, uma
 * versão "achatada" (uma linha por resposta, já com o chamado e a
 * entidade) pensada pra ferramentas de BI/relatório externas
 * consultarem direto, sem precisar conhecer o schema interno das
 * tabelas do plugin nem fazer parse manual de JSON pros tipos
 * checkbox/lista com comentário.
 *
 * @return void
 */
function plugin_satisfacao_install_view(): void
{
    global $DB;

    $DB->doQuery(
        "CREATE OR REPLACE VIEW `glpi_plugin_satisfacao_vw_answers` AS
        SELECT
            st.id                    AS survey_id,
            st.tickets_id            AS tickets_id,
            t.name                   AS ticket_title,
            st.entities_id           AS entities_id,
            e.completename           AS entity_name,
            st.date_begin            AS ticket_closed_date,
            st.date_answered         AS survey_answered_date,
            st.status                AS survey_status,
            (CASE st.status
                WHEN 1 THEN 'Aguardando resposta'
                WHEN 2 THEN 'Respondida'
                ELSE NULL
            END)                     AS survey_status_label,
            st.users_id_answered     AS answered_by_users_id,
            ua.name                  AS answered_by_login,
            q.id                     AS question_id,
            q.name                   AS question_name,
            q.type                   AS question_type,
            q.rank                   AS question_rank,
            a.id                     AS answer_id,
            a.answer                 AS answer_raw,
            (CASE
                WHEN q.type IN ('checkbox', 'dropdown') AND JSON_VALID(a.answer)
                    THEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.answer, '\$.selected')), a.answer)
                ELSE a.answer
            END)                     AS answer_value,
            (CASE
                WHEN q.type IN ('checkbox', 'dropdown') AND JSON_VALID(a.answer)
                    THEN JSON_UNQUOTE(JSON_EXTRACT(a.answer, '\$.other'))
                ELSE NULL
            END)                     AS answer_other_comment
        FROM `glpi_plugin_satisfacao_answers` a
        INNER JOIN `glpi_plugin_satisfacao_surveytickets` st ON st.id = a.plugin_satisfacao_tickets_id
        INNER JOIN `glpi_plugin_satisfacao_questions` q ON q.id = a.plugin_satisfacao_questions_id
        INNER JOIN `glpi_tickets` t ON t.id = st.tickets_id
        LEFT JOIN `glpi_entities` e ON e.id = st.entities_id
        LEFT JOIN `glpi_users` ua ON ua.id = st.users_id_answered;"
    ) or die($DB->error());
}

/**
 * Remove as tabelas do plugin na desinstalação.
 *
 * @return boolean
 */
function plugin_satisfacao_uninstall()
{
    global $DB;

    $DB->doQuery('DROP VIEW IF EXISTS `glpi_plugin_satisfacao_vw_answers`');

    foreach ([
        'glpi_plugin_satisfacao_answers',
        'glpi_plugin_satisfacao_surveytickets',
        'glpi_plugin_satisfacao_questions',
        'glpi_plugin_satisfacao_surveysettings',
    ] as $table) {
        $DB->doQuery("DROP TABLE IF EXISTS `{$table}`");
    }

    plugin_satisfacao_uninstall_notification();

    return true;
}

/**
 * Cria, se ainda não existir, a notificação padrão (evento
 * "satisfacao_survey" em Ticket) disparada quando o plugin gera uma
 * pesquisa. Idempotente: não faz nada se já existir uma notificação pra
 * esse par (itemtype, event) — evita duplicar em reinstalação/update.
 *
 * @return void
 */
function plugin_satisfacao_install_notification(): void
{
    $existing = new \Notification();
    if ($existing->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'satisfacao_survey'])) {
        // Já existe (instalação anterior) — só garante que o destinatário
        // "Solicitante" está configurado, caso essa instalação seja de
        // antes dessa correção (a notificação existia sem destinatário).
        if (countElementsInTable('glpi_notificationtargets', ['notifications_id' => $existing->getID()]) === 0) {
            (new \NotificationTarget())->add([
                'items_id'         => \Notification::AUTHOR,
                'type'             => \Notification::USER_TYPE,
                'notifications_id' => $existing->getID(),
            ]);
        }
        return;
    }

    $template = new \NotificationTemplate();
    $templates_id = $template->add([
        'name'     => 'Pesquisa de satisfação disponível',
        'itemtype' => 'Ticket',
        'comment'  => 'Criado automaticamente pelo plugin Pesquisa de Satisfação.',
    ]);
    if (!$templates_id) {
        return;
    }

    $translation = new \NotificationTemplateTranslation();
    $translation->add([
        'notificationtemplates_id' => $templates_id,
        'language'                 => '',
        'subject'                  => __('Pesquisa de satisfação disponível', 'satisfacao') . ': ##ticket.title##',
        'content_text'             => implode("\n\n", [
            '##lang.ticket.title## : ##ticket.title##',
            __('O chamado foi fechado e uma pesquisa de satisfação está disponível para resposta.', 'satisfacao'),
            __('Acesse o chamado para responder', 'satisfacao') . ': ##ticket.url##',
        ]),
        'content_html' => '<p>##lang.ticket.title## : ##ticket.title##</p>'
            . '<p>' . __('O chamado foi fechado e uma pesquisa de satisfação está disponível para resposta.', 'satisfacao') . '</p>'
            . '<p>' . __('Acesse o chamado para responder', 'satisfacao') . ': <a href="##ticket.url##">##ticket.url##</a></p>',
    ]);

    $notification = new \Notification();
    $notifications_id = $notification->add([
        'name'         => 'Pesquisa de satisfação disponível',
        'entities_id'  => 0,
        'is_recursive' => 1,
        'itemtype'     => 'Ticket',
        'event'        => 'satisfacao_survey',
        'is_active'    => 1,
        'comment'      => 'Criado automaticamente pelo plugin Pesquisa de Satisfação.',
    ]);
    if (!$notifications_id) {
        return;
    }

    $link = new \Notification_NotificationTemplate();
    $link->add([
        'notifications_id'         => $notifications_id,
        'mode'                     => 'mailing',
        'notificationtemplates_id' => $templates_id,
    ]);

    // Destinatário: o solicitante do chamado. Sem essa linha em
    // glpi_notificationtargets a notificação não tem pra quem mandar —
    // Notification::AUTHOR é o "Requester" nos objetos ITIL (é assim que
    // o core rotula esse mesmo destino na notificação nativa "satisfaction").
    $target = new \NotificationTarget();
    $target->add([
        'items_id'         => \Notification::AUTHOR,
        'type'             => \Notification::USER_TYPE,
        'notifications_id' => $notifications_id,
    ]);
}

/**
 * Remove a notificação, o template, a tradução e o vínculo criados por
 * plugin_satisfacao_install_notification().
 *
 * @return void
 */
function plugin_satisfacao_uninstall_notification(): void
{
    $notification = new \Notification();
    foreach ($notification->find(['itemtype' => 'Ticket', 'event' => 'satisfacao_survey']) as $row) {
        $templates_id = null;

        $link = new \Notification_NotificationTemplate();
        foreach ($link->find(['notifications_id' => $row['id']]) as $link_row) {
            $templates_id = (int) $link_row['notificationtemplates_id'];
            $link->delete(['id' => $link_row['id']], true);
        }

        $target = new \NotificationTarget();
        foreach ($target->find(['notifications_id' => $row['id']]) as $target_row) {
            $target->delete(['id' => $target_row['id']], true);
        }

        $notification->delete(['id' => $row['id']], true);

        if ($templates_id) {
            $translation = new \NotificationTemplateTranslation();
            foreach ($translation->find(['notificationtemplates_id' => $templates_id]) as $translation_row) {
                $translation->delete(['id' => $translation_row['id']], true);
            }

            $template = new \NotificationTemplate();
            $template->delete(['id' => $templates_id], true);
        }
    }
}

/**
 * Detecta o fechamento de um chamado (mudança de status para Fechado) e
 * cria a pesquisa de satisfação correspondente, se ainda não existir.
 *
 * @param \Ticket $item
 *
 * @return void
 */
function plugin_satisfacao_ticket_update(\Ticket $item)
{
    if (!array_key_exists('status', $item->oldvalues ?? [])) {
        return;
    }

    if ((int) $item->fields['status'] !== \Ticket::CLOSED) {
        return;
    }

    if ((int) $item->oldvalues['status'] === \Ticket::CLOSED) {
        return;
    }

    SurveyTicket::createForTicket($item);
}

/**
 * Adiciona a pesquisa de satisfação do chamado (se existir) como item da
 * timeline (hook `timeline_items`). O array `timeline` do parâmetro é
 * passado por referência pelo core.
 *
 * @param array $params ['item' => CommonITILObject, 'timeline' => array (por referência)]
 *
 * @return void
 */
function plugin_satisfacao_timeline_items(array $params): void
{
    $item = $params['item'];
    if (!($item instanceof \Ticket)) {
        return;
    }

    $survey = SurveyTicket::getForTicket((int) $item->getID());
    if ($survey === null) {
        return;
    }

    $answered_by = (int) ($survey->fields['users_id_answered'] ?? 0);

    $params['timeline']['GlpiPluginSatisfacaoSurveyTicket_' . $survey->getID()] = [
        'type'   => SurveyTicket::class,
        'item'   => [
            'id'                => $survey->getID(),
            'users_id'          => $answered_by > 0 ? $answered_by : null,
            'date'              => $survey->fields['date_answered'] ?: $survey->fields['date_begin'],
            'date_creation'     => $survey->fields['date_begin'],
            'timeline_position' => \CommonITILObject::TIMELINE_LEFT,
            'can_edit'          => false,
        ],
        'object' => $survey,
    ];
}
