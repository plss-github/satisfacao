<?php

/**
 * -------------------------------------------------------------------------
 * Satisfacao plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * Pesquisa de satisfação com perguntas configuráveis, disparada
 * automaticamente quando um chamado é fechado.
 * -------------------------------------------------------------------------
 */

define('PLUGIN_SATISFACAO_VERSION', '1.0.0');
define('PLUGIN_SATISFACAO_MIN_GLPI', '11.0');
define('PLUGIN_SATISFACAO_MAX_GLPI', '11.9.99');

// URL pública do plugin (GLPI 11: Plugin::getWebDir() está @deprecated
// desde 11.0 — "All plugins resources should be accessed from the
// `/plugins/` path"). Usada nos front/*.php e no formulário da pesquisa.
global $CFG_GLPI;
define('PLUGIN_SATISFACAO_WEBDIR', ($CFG_GLPI['root_doc'] ?? '') . '/plugins/satisfacao');

/**
 * Init hooks of the plugin.
 *
 * @return void
 */
function plugin_init_satisfacao()
{
    global $PLUGIN_HOOKS;

    // Dispara a criação da pesquisa quando um chamado é atualizado
    // (verificamos dentro do hook.php se a mudança foi para status Fechado).
    $PLUGIN_HOOKS['item_update']['satisfacao'] = [
        'Ticket' => 'plugin_satisfacao_ticket_update',
    ];

    // Registra o evento "satisfacao_survey" no alvo de notificação nativo
    // de Ticket (aparece no combo de eventos ao configurar uma
    // notificação pra Ticket). Fica fora de qualquer checagem de sessão
    // porque precisa disparar também via API/cron.
    $PLUGIN_HOOKS['item_get_events']['satisfacao'] = [
        \NotificationTargetTicket::class => [
            \GlpiPlugin\Satisfacao\NotificationTargetTicket::class,
            'addEvents',
        ],
    ];

    // Ícone de engrenagem em Configurar > Plugins.
    $PLUGIN_HOOKS['config_page']['satisfacao'] = 'front/question.php';

    // Entrada no menu Administração, só pra quem tem o direito de
    // configurar (mesmo padrão dos plugins oficiais example/satisfaction).
    if (Session::getLoginUserID() && Session::haveRight('config', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['satisfacao'] = [
            'admin' => \GlpiPlugin\Satisfacao\Menu::class,
        ];
    }

    // Adiciona a aba "Pesquisa de satisfação" na tela do chamado.
    Plugin::registerClass(\GlpiPlugin\Satisfacao\SurveyTicket::class, [
        'addtabon' => ['Ticket'],
    ]);

    // Registra o tipo "pesquisa de satisfação" e injeta a pesquisa como
    // item da timeline do chamado (mesmo mecanismo usado por
    // acompanhamentos/tarefas/soluções).
    // 'timeline_answer_actions' é resolvido via is_callable() direto pelo
    // core (sem garantir que hook.php já foi incluído), por isso usamos
    // um callable de array apontando pra um método estático da classe
    // (autoload PSR-4), não uma função de hook.php.
    $PLUGIN_HOOKS['timeline_answer_actions']['satisfacao'] = [
        \GlpiPlugin\Satisfacao\SurveyTicket::class,
        'getTimelineAnswerActions',
    ];
    $PLUGIN_HOOKS['timeline_items']['satisfacao'] = 'plugin_satisfacao_timeline_items';
}

/**
 * Get the name and the version of the plugin.
 *
 * @return array
 */
function plugin_version_satisfacao()
{
    return [
        'name'         => 'Pesquisa de Satisfação',
        'version'      => PLUGIN_SATISFACAO_VERSION,
        'author'       => 'Matheus Schmidt',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SATISFACAO_MIN_GLPI,
                'max' => PLUGIN_SATISFACAO_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install.
 *
 * @return boolean
 */
function plugin_satisfacao_check_prerequisites()
{
    return true;
}

/**
 * Check configuration process.
 *
 * @param boolean $verbose Whether to display message on failure.
 *
 * @return boolean
 */
function plugin_satisfacao_check_config($verbose = false)
{
    return true;
}
