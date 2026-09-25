<?php

namespace GlpiPlugin\Satisfacao;

use CommonGLPI;

/**
 * Entrada do plugin no menu "Administração" do GLPI, registrada via
 * `$PLUGIN_HOOKS['menu_toadd']['satisfacao'] = ['admin' => Menu::class]`
 * em setup.php. Só é registrado se o usuário logado tiver o direito
 * `config` (ver plugin_init_satisfacao()), então este método não repete
 * a checagem de direito.
 */
class Menu extends CommonGLPI
{
    public static function getMenuName()
    {
        return __('Pesquisa de Satisfação', 'satisfacao');
    }

    public static function getIcon()
    {
        return 'ti ti-star';
    }

    /**
     * Estrutura do menu: entrada principal (Perguntas) + submenu com
     * Mensagens e Resultados.
     *
     * @return array
     */
    public static function getMenuContent()
    {
        $question_url = PLUGIN_SATISFACAO_WEBDIR . '/front/question.php';

        return [
            'title' => self::getMenuName(),
            'page'  => $question_url,
            'icon'  => self::getIcon(),
            'options' => [
                'question' => [
                    'title' => Question::getTypeName(2),
                    'page'  => $question_url,
                    'icon'  => self::getIcon(),
                    'links' => [
                        'add' => Question::getFormURL(false),
                    ],
                ],
                'settings' => [
                    'title' => SurveySettings::getTypeName(2),
                    'page'  => PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php',
                    'icon'  => 'ti ti-message-2',
                ],
                'result' => [
                    'title' => __('Resultados', 'satisfacao'),
                    'page'  => PLUGIN_SATISFACAO_WEBDIR . '/front/result.php',
                    'icon'  => 'ti ti-report-analytics',
                ],
            ],
        ];
    }
}
