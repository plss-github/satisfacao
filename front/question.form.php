<?php

use GlpiPlugin\Satisfacao\Question;

Session::checkRight('config', UPDATE);

$question = new Question();

if (isset($_POST['add'])) {
    $question->check(-1, CREATE, $_POST);
    $newID = $question->add($_POST);
    Html::redirect($question->getFormURL() . '?id=' . $newID);
} elseif (isset($_POST['update'])) {
    $question->check($_POST['id'], UPDATE);
    $question->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $question->check($_POST['id'], PURGE);
    $question->delete($_POST, 1);
    Html::redirect(PLUGIN_SATISFACAO_WEBDIR . '/front/question.php');
} else {
    Html::header(Question::getTypeName(1), PLUGIN_SATISFACAO_WEBDIR . '/front/question.form.php', 'admin', \GlpiPlugin\Satisfacao\Menu::class);
    $id = (int) ($_GET['id'] ?? -1);
    $question->showForm($id);
    Html::footer();
}
