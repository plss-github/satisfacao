<?php

namespace GlpiPlugin\Satisfacao;

use CommonDBTM;
use CommonITILActor;
use Group_Ticket;
use Session;
use Ticket;
use Ticket_User;

/**
 * Mensagens de cabeçalho/agradecimento e critérios de exclusão de disparo
 * da pesquisa, por entidade. Uma linha por entidade (chave única em
 * `entities_id`); se não existir linha para a entidade, o comportamento é
 * "sem mensagem configurada" e "nenhuma exclusão".
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
     * Salva (cria ou atualiza) as mensagens e os critérios de exclusão de
     * uma entidade.
     *
     * @param integer     $entities_id
     * @param string|null $header_message
     * @param string|null $thankyou_message
     * @param int[]       $excluded_requesttypes IDs de RequestType (origem da requisição) a excluir.
     * @param int[]       $excluded_categories   IDs de ITILCategory a excluir.
     * @param int[]       $excluded_users        IDs de usuário (solicitante) a excluir.
     * @param int[]       $excluded_groups       IDs de grupo (solicitante) a excluir.
     *
     * @return void
     */
    public static function saveForEntity(
        int $entities_id,
        ?string $header_message,
        ?string $thankyou_message,
        array $excluded_requesttypes = [],
        array $excluded_categories = [],
        array $excluded_users = [],
        array $excluded_groups = []
    ): void {
        $header_message   = ($header_message !== null && trim($header_message) !== '') ? $header_message : null;
        $thankyou_message = ($thankyou_message !== null && trim($thankyou_message) !== '') ? $thankyou_message : null;

        $input = [
            'header_message'        => $header_message,
            'thankyou_message'      => $thankyou_message,
            'excluded_requesttypes' => self::encodeIdList($excluded_requesttypes),
            'excluded_categories'   => self::encodeIdList($excluded_categories),
            'excluded_users'        => self::encodeIdList($excluded_users),
            'excluded_groups'       => self::encodeIdList($excluded_groups),
        ];

        $settings = new self();
        $existing_id = null;
        if ($settings->getFromDBByCrit(['entities_id' => $entities_id])) {
            $existing_id = $settings->getID();
        }

        if ($existing_id !== null) {
            $input['id'] = $existing_id;
            $settings->update($input);
        } else {
            $input['entities_id'] = $entities_id;
            $settings->add($input);
        }
    }

    /**
     * Converte uma lista de IDs em JSON para gravar no banco (null se
     * vazia, pra manter a coluna limpa quando não há exclusão).
     *
     * @param int[] $ids
     *
     * @return string|null
     */
    private static function encodeIdList(array $ids): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return !empty($ids) ? json_encode($ids) : null;
    }

    /**
     * Decodifica uma coluna de exclusão (JSON) em array de IDs.
     *
     * @param string|null $json
     *
     * @return int[]
     */
    private static function decodeIdList(?string $json): array
    {
        if (empty($json)) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /**
     * Lista de IDs excluídos de cada critério, já decodificada, pronta
     * pra alimentar os multi-selects do formulário de configurações.
     *
     * @return array{requesttypes: int[], categories: int[], users: int[], groups: int[]}
     */
    public function getExclusions(): array
    {
        return [
            'requesttypes' => self::decodeIdList($this->fields['excluded_requesttypes'] ?? null),
            'categories'   => self::decodeIdList($this->fields['excluded_categories'] ?? null),
            'users'        => self::decodeIdList($this->fields['excluded_users'] ?? null),
            'groups'       => self::decodeIdList($this->fields['excluded_groups'] ?? null),
        ];
    }

    /**
     * Verifica se a pesquisa NÃO deve ser gerada para este chamado, de
     * acordo com os critérios de exclusão configurados na entidade dele
     * (origem da requisição, categoria, solicitante ou grupo do
     * solicitante — ex.: chamados abertos por ferramentas de
     * monitoramento/integrações). Sem configuração pra entidade, nada é
     * excluído.
     *
     * @param Ticket $ticket
     *
     * @return boolean
     */
    public static function ticketIsExcluded(Ticket $ticket): bool
    {
        $settings = self::getForEntity((int) $ticket->fields['entities_id']);
        if ($settings->isNewItem()) {
            return false;
        }

        $exclusions = $settings->getExclusions();

        if (
            !empty($exclusions['requesttypes'])
            && in_array((int) $ticket->fields['requesttypes_id'], $exclusions['requesttypes'], true)
        ) {
            return true;
        }

        if (
            !empty($exclusions['categories'])
            && in_array((int) $ticket->fields['itilcategories_id'], $exclusions['categories'], true)
        ) {
            return true;
        }

        if (!empty($exclusions['users'])) {
            $requester_ids = array_column(
                (new Ticket_User())->find([
                    'tickets_id' => $ticket->getID(),
                    'type'       => CommonITILActor::REQUESTER,
                ]),
                'users_id'
            );
            if (array_intersect($exclusions['users'], array_map('intval', $requester_ids))) {
                return true;
            }
        }

        if (!empty($exclusions['groups'])) {
            $requester_group_ids = array_column(
                (new Group_Ticket())->find([
                    'tickets_id' => $ticket->getID(),
                    'type'       => CommonITILActor::REQUESTER,
                ]),
                'groups_id'
            );
            if (array_intersect($exclusions['groups'], array_map('intval', $requester_group_ids))) {
                return true;
            }
        }

        return false;
    }
}
