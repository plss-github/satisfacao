<?php

namespace GlpiPlugin\Satisfacao;

use CommonDBTM;
use Dropdown;
use Entity;
use Html;
use Session;

/**
 * Pergunta cadastrada para a pesquisa de satisfação.
 */
class Question extends CommonDBTM
{
    public static $rightname = 'config';

    public const TYPE_RATING   = 'rating';
    public const TYPE_TEXT     = 'text';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_DROPDOWN = 'dropdown';

    public static function getTypeName($nb = 0)
    {
        return _n('Pergunta da pesquisa', 'Perguntas da pesquisa', $nb, 'satisfacao');
    }

    /**
     * O direito "config" do GLPI só define os bits READ/UPDATE (não tem
     * CREATE/PURGE), então os métodos can*() padrão (baseados em
     * Session::haveRight(static::$rightname, CREATE|PURGE)) nunca
     * retornam verdadeiro. Sobrescrevemos para mapear tudo em READ/UPDATE.
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
     * Tipos de campo suportados pelo editor de perguntas.
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_RATING   => __('Nota (1 a 5)', 'satisfacao'),
            self::TYPE_TEXT     => __('Texto livre', 'satisfacao'),
            self::TYPE_CHECKBOX => __('Caixa de seleção (múltipla escolha)', 'satisfacao'),
            self::TYPE_DROPDOWN => __('Lista suspensa (escolha única)', 'satisfacao'),
        ];
    }

    /**
     * Indica se o tipo de campo precisa de uma lista de opções.
     *
     * @param string $type
     *
     * @return boolean
     */
    public static function typeHasOptions(string $type): bool
    {
        return in_array($type, [self::TYPE_CHECKBOX, self::TYPE_DROPDOWN], true);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    /**
     * Valida o tipo e converte o textarea de opções (uma por linha) em JSON.
     *
     * @param array $input
     *
     * @return array|boolean
     */
    private function prepareInput($input)
    {
        if (isset($input['type']) && !array_key_exists($input['type'], self::getTypes())) {
            Session::addMessageAfterRedirect(__('Tipo de pergunta inválido.', 'satisfacao'), false, ERROR);
            return false;
        }

        if (array_key_exists('options_raw', $input)) {
            $type = $input['type'] ?? ($this->fields['type'] ?? '');
            if (self::typeHasOptions($type)) {
                $lines = preg_split('/\r\n|\r|\n/', (string) $input['options_raw']);
                $lines = array_values(array_filter(
                    array_map('trim', $lines),
                    static function ($line) {
                        return $line !== '';
                    }
                ));
                $input['options'] = json_encode($lines);
            } else {
                $input['options'] = null;
            }
            unset($input['options_raw']);
        }

        if (array_key_exists('other_option_label', $input)) {
            $type = $input['type'] ?? ($this->fields['type'] ?? '');
            $label = trim((string) $input['other_option_label']);
            $input['other_option_label'] = (self::typeHasOptions($type) && $label !== '') ? $label : null;
        }

        return $input;
    }

    /**
     * Lista de opções configuradas (checkbox/dropdown).
     *
     * @return array
     */
    public function getOptionsArray(): array
    {
        if (empty($this->fields['options'])) {
            return [];
        }
        $decoded = json_decode($this->fields['options'], true);
        return is_array($decoded) ? $decoded : [];
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
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Nome'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'         => '2',
            'table'      => self::getTable(),
            'field'      => 'type',
            'name'       => __('Tipo de campo', 'satisfacao'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];

        $tab[] = [
            'id'       => '3',
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Ativa', 'satisfacao'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => self::getTable(),
            'field'    => 'is_mandatory',
            'name'     => __('Obrigatória', 'satisfacao'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => '5',
            'table'    => self::getTable(),
            'field'    => 'rank',
            'name'     => __('Ordem de exibição', 'satisfacao'),
            'datatype' => 'number',
        ];

        $tab[] = [
            'id'       => '19',
            'table'    => self::getTable(),
            'field'    => 'date_mod',
            'name'     => __('Última modificação'),
            'datatype' => 'datetime',
        ];

        // Coluna "Entidade" — sem isso o motor de busca do GLPI não
        // oferece a entidade em "Selecionar itens padrão a exibir"
        // (não é automático pra itemtypes de plugin, ver README).
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
        if ($field === 'type') {
            $types = self::getTypes();
            return $types[$values['type']] ?? $values['type'];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Nome') . '</td>';
        echo '<td>';
        echo Html::input('name', ['value' => $this->fields['name'] ?? '']);
        echo '</td>';
        echo '<td>' . __('Ativa', 'satisfacao') . '</td>';
        echo '<td>';
        Html::showCheckbox(['name' => 'is_active', 'checked' => !isset($this->fields['is_active']) || (bool) $this->fields['is_active']]);
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Tipo de campo', 'satisfacao') . '</td>';
        echo '<td>';
        Dropdown::showFromArray('type', self::getTypes(), [
            'value' => $this->fields['type'] ?? self::TYPE_RATING,
        ]);
        echo '</td>';
        echo '<td>' . __('Obrigatória', 'satisfacao') . '</td>';
        echo '<td>';
        Html::showCheckbox(['name' => 'is_mandatory', 'checked' => (bool) ($this->fields['is_mandatory'] ?? false)]);
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Opções (uma por linha, só para caixa de seleção e lista)', 'satisfacao') . '</td>';
        echo '<td colspan="3">';
        echo '<textarea name="options_raw" rows="4" style="width:100%">';
        echo htmlescape(implode("\n", $this->getOptionsArray()));
        echo '</textarea>';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Opção que libera campo de comentário livre', 'satisfacao') . '</td>';
        echo '<td colspan="3">';
        echo Html::input('other_option_label', ['value' => $this->fields['other_option_label'] ?? '', 'size' => 60]);
        echo '<div class="text-muted mt-1">' . __(
            'Só pra caixa de seleção e lista. Cole aqui o texto exato de uma das opções acima (ex: "OUTROS, por favor comente."); ao marcar/selecionar essa opção, aparece uma caixa de texto livre pro respondente comentar. Deixe em branco se nenhuma opção precisar disso.',
            'satisfacao'
        ) . '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>' . __('Ordem de exibição', 'satisfacao') . '</td>';
        echo '<td>';
        Dropdown::showNumber('rank', ['value' => $this->fields['rank'] ?? 0, 'min' => 0, 'max' => 100]);
        echo '</td>';
        echo '<td>' . Entity::getTypeName(1) . '</td>';
        echo '<td>';
        Entity::dropdown(['value' => $this->fields['entities_id'] ?? $_SESSION['glpiactive_entity']]);
        echo '</td>';
        echo '</tr>';

        $this->showFormButtons($options);

        return true;
    }
}
