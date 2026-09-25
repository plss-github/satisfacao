<?php

namespace GlpiPlugin\Satisfacao;

use CommonDBTM;

/**
 * Resposta de uma pergunta para a pesquisa de satisfação de um chamado.
 */
class Answer extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Resposta', 'Respostas', $nb, 'satisfacao');
    }
}
