<?php

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Satisfacao\Answer;
use GlpiPlugin\Satisfacao\Question;
use GlpiPlugin\Satisfacao\SurveyTicket;

Session::checkLoginUser();
// O kernel do GLPI 11 já valida (e consome) o token CSRF automaticamente
// para requisições POST não-XHR antes do script ser executado
// (Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener); uma
// segunda chamada a Session::checkCSRF() aqui falharia sempre, pois o
// token já teria sido removido da sessão pela checagem do kernel.

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$survey_id  = (int) ($_POST['id'] ?? 0);

$ticket = new Ticket();
if (!$tickets_id || !$ticket->getFromDB($tickets_id) || !$ticket->can($tickets_id, READ)) {
    throw new AccessDeniedHttpException();
}

$survey = new SurveyTicket();
if (!$survey->getFromDB($survey_id) || (int) $survey->fields['tickets_id'] !== $tickets_id) {
    throw new AccessDeniedHttpException();
}

$redirect_url = $ticket->getFormURL() . '?id=' . $tickets_id;

if ((int) $survey->fields['status'] === SurveyTicket::STATUS_ANSWERED) {
    Session::addMessageAfterRedirect(__('Esta pesquisa já foi respondida.', 'satisfacao'), false, ERROR);
    Html::redirect($redirect_url);
}

if (!$ticket->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())) {
    throw new AccessDeniedHttpException();
}

$questions = (new Question())->find([
    'entities_id' => $survey->fields['entities_id'],
    'is_active'   => 1,
]);

foreach ($questions as $question) {
    $qid   = (int) $question['id'];
    $field = 'answer_' . $qid;

    if ($question['is_mandatory']) {
        if ($question['type'] === Question::TYPE_CHECKBOX) {
            $missing = empty($_POST[$field]);
        } else {
            $missing = !isset($_POST[$field]) || trim((string) $_POST[$field]) === '';
        }

        if ($missing) {
            Session::addMessageAfterRedirect(
                sprintf(__('A pergunta "%s" é obrigatória.', 'satisfacao'), $question['name']),
                false,
                ERROR
            );
            Html::redirect($redirect_url);
        }
    }

    $value       = $_POST[$field] ?? null;
    $other_label = $question['other_option_label'] ?? null;
    $has_other   = !empty($other_label);

    if ($question['type'] === Question::TYPE_CHECKBOX) {
        $selected = array_map('strval', (array) $value);
        if ($has_other) {
            $other_text = trim((string) ($_POST[$field . '_other'] ?? ''));
            $value = json_encode(['selected' => $selected, 'other' => $other_text !== '' ? $other_text : null]);
        } else {
            $value = json_encode($selected);
        }
    } elseif ($question['type'] === Question::TYPE_DROPDOWN && $has_other) {
        $other_text = trim((string) ($_POST[$field . '_other'] ?? ''));
        $value = json_encode(['selected' => (string) $value, 'other' => $other_text !== '' ? $other_text : null]);
    }

    $answer = new Answer();
    $answer->add([
        'plugin_satisfacao_tickets_id'   => $survey->getID(),
        'plugin_satisfacao_questions_id' => $qid,
        'answer'                         => $value,
    ]);
}

$survey->update([
    'id'                => $survey->getID(),
    'status'            => SurveyTicket::STATUS_ANSWERED,
    'date_answered'     => date('Y-m-d H:i:s'),
    'users_id_answered' => Session::getLoginUserID(),
]);

Session::addMessageAfterRedirect(__('Obrigado por responder a pesquisa!', 'satisfacao'));
Html::redirect($redirect_url);
