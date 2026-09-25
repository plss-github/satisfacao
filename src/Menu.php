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

    /**
     * Sub-navegação (abas Perguntas/Mensagens/Resultados) exibida no topo
     * das telas de configuração do plugin, usando o mesmo padrão de abas
     * (`nav nav-tabs`) do restante do GLPI. Complementa o submenu lateral
     * de Administração com um acesso rápido dentro do próprio conteúdo.
     *
     * @param string $active Chave da aba ativa: 'question', 'settings' ou 'result'.
     *
     * @return void
     */
    public static function renderSubNav(string $active): void
    {
        $tabs = [
            'question' => [
                'label' => Question::getTypeName(2),
                'url'   => PLUGIN_SATISFACAO_WEBDIR . '/front/question.php',
                'icon'  => self::getIcon(),
            ],
            'settings' => [
                'label' => SurveySettings::getTypeName(2),
                'url'   => PLUGIN_SATISFACAO_WEBDIR . '/front/settings.php',
                'icon'  => 'ti ti-message-2',
            ],
            'result' => [
                'label' => __('Resultados', 'satisfacao'),
                'url'   => PLUGIN_SATISFACAO_WEBDIR . '/front/result.php',
                'icon'  => 'ti ti-report-analytics',
            ],
        ];

        echo '<ul class="nav nav-tabs mb-3">';
        foreach ($tabs as $key => $tab) {
            $active_class = ($key === $active) ? ' active' : '';
            echo '<li class="nav-item">';
            echo '<a class="nav-link' . $active_class . '" href="' . $tab['url'] . '">';
            echo '<i class="' . $tab['icon'] . ' me-1"></i>' . htmlescape($tab['label']);
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Ícone "?" com tooltip (padrão GLPI:
     * templates/components/form/fields_macros.html.twig), pra explicações
     * de campo que ficariam soltas como texto abaixo do campo.
     *
     * @param string $text Texto do tooltip (não deve conter HTML).
     *
     * @return string
     */
    public static function helpIcon(string $text): string
    {
        return "<span class='form-help' style='margin-left:3px' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-html='true' data-bs-title='"
            . htmlescape($text) . "'>?</span>";
    }
}
