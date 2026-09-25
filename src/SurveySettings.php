<?php

namespace GlpiPlugin\Satisfacao;

use CommonDBTM;
use Session;

/**
 * Mensagens de cabeçalho e de agradecimento da pesquisa, por entidade.
 * Uma linha por entidade (chave única em `entities_id`); se não existir
 * linha para a entidade, o comportamento é "sem mensagem configurada".
 */
class SurveySettings extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Configuração da pesquisa', 'Configurações da pesquisa', $nb, 'satisfacao');
    }

    /**
     * O direito "config" do GLPI só define os bits READ/UPDATE — ver
     * mesma observação em Question::canCreate().
     */
    public static function canCreate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canPurge(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    /**
     * Busca a configuração de uma entidade. Retorna uma instância nova
     * (não salva, `isNewItem() === true`) se a entidade ainda não tem
     * mensagens configuradas.
     *
     * @param integer $entities_id
     *
     * @return self
     */
    public static function getForEntity(int $entities_id): self
    {
        $settings = new self();
        if (!$settings->getFromDBByCrit(['entities_id' => $entities_id])) {
            $settings->getEmpty();
            $settings->fields['entities_id'] = $entities_id;
        }
        return $settings;
    }

    /**
     * Salva (cria ou atualiza) as mensagens de uma entidade.
     *
     * @param integer     $entities_id
     * @param string|null $header_message
     * @param string|null $thankyou_message
     *
     * @return void
     */
    public static function saveForEntity(int $entities_id, ?string $header_message, ?string $thankyou_message): void
    {
        $header_message   = ($header_message !== null && trim($header_message) !== '') ? $header_message : null;
        $thankyou_message = ($thankyou_message !== null && trim($thankyou_message) !== '') ? $thankyou_message : null;

        $settings = new self();
        $existing_id = null;
        if ($settings->getFromDBByCrit(['entities_id' => $entities_id])) {
            $existing_id = $settings->getID();
        }

        if ($existing_id !== null) {
            $settings->update([
                'id'               => $existing_id,
                'header_message'   => $header_message,
                'thankyou_message' => $thankyou_message,
            ]);
        } else {
            $settings->add([
                'entities_id'      => $entities_id,
                'header_message'   => $header_message,
                'thankyou_message' => $thankyou_message,
            ]);
        }
    }
}
