<?php

namespace GlpiPlugin\Satisfacao;

/**
 * Adiciona o evento "satisfacao_survey" ao alvo de notificação nativo de
 * Ticket, via hook `item_get_events`. Não é instanciada diretamente pelo
 * core (GLPI sempre resolve `NotificationTargetTicket`, a classe nativa,
 * pra qualquer evento de Ticket) — serve só de espaço de nomes pro
 * callback estático `addEvents()` e pra reaproveitar `getEvents()`.
 */
class NotificationTargetTicket extends \NotificationTargetTicket
{
    public function getEvents(): array
    {
        return self::pluginEvents();
    }

    /**
     * Eventos do plugin, sem precisar instanciar a classe.
     *
     * @return array<string, string>
     */
    public static function pluginEvents(): array
    {
        return ['satisfacao_survey' => __('Pesquisa de satisfação disponível', 'satisfacao')];
    }

    /**
     * Callback do hook `item_get_events` (itemtype `\NotificationTargetTicket`).
     * Recebe a instância nativa já criada pelo core e injeta o evento do
     * plugin nela, pra aparecer no combo de eventos ao configurar uma
     * notificação de Ticket.
     *
     * @param \NotificationTargetTicket $target
     *
     * @return void
     */
    public static function addEvents(\NotificationTargetTicket $target): void
    {
        // Não instanciar `new self()` aqui: o construtor de NotificationTarget
        // carrega todos os destinatários possíveis (grupos, perfis...), e este
        // hook roda a cada evento de chamado disparado pelo core.
        $target->events += self::pluginEvents();
    }
}
