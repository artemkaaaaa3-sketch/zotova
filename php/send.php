<?php
/**
 * Приём заявки с формы записи. Кладётся рядом с index.html на любой хостинг с PHP.
 * Ничего не хранит на сервере: письмо уходит на почту и всё.
 *
 * Перед загрузкой на хостинг заменить адрес в $TO.
 */

$TO      = 'zotova.psy@yandex.ru';          // куда приходят заявки
$SUBJECT = 'Запись на первую встречу';
$FROM    = 'noreply@zotova-psy.ru';         // домен сайта, иначе письмо уйдёт в спам

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Только POST');
}

// Ловушка для роботов: поле скрыто от человека, заполнить его может только скрипт.
if (!empty($_POST['website'])) {
    exit('ok');                              // молча, чтобы робот не подбирал обход
}

// Форму, заполненную быстрее трёх секунд, человек заполнить не успевает.
$ts = isset($_POST['ts']) ? (int) $_POST['ts'] : 0;
if ($ts > 0 && (microtime(true) * 1000 - $ts) < 3000) {
    exit('ok');
}

function field(string $key, int $max = 500): string
{
    $v = isset($_POST[$key]) ? (string) $_POST[$key] : '';
    $v = str_replace(["\r", "\n", "\0"], ' ', $v);   // заголовки письма не подделать
    $v = trim(mb_substr($v, 0, $max));
    return $v;
}

$name    = field('name', 80);
$way     = field('way', 20);
$contact = field('contact', 120);
$fmt     = field('fmt', 30);
$when    = field('when', 200);
$about   = trim(mb_substr((string) ($_POST['about'] ?? ''), 0, 2000));
$agree   = !empty($_POST['agree']);

if ($name === '' || $contact === '' || !$agree) {
    http_response_code(422);
    exit('Не хватает имени, контакта или согласия на обработку данных');
}

$allowedWays = ['телефон', 'почта', 'телеграм'];
if (!in_array($way, $allowedWays, true)) {
    $way = 'контакт';
}

$body = implode("\n", [
    'Имя: ' . $name,
    'Связь (' . $way . '): ' . $contact,
    'Формат: ' . ($fmt !== '' ? $fmt : 'не выбран'),
    'Удобное время: ' . ($when !== '' ? $when : 'не указано'),
    '',
    'С чем придёт:',
    $about !== '' ? $about : 'не написал',
    '',
    '— отправлено с сайта, ' . date('d.m.Y H:i'),
]);

$headers = implode("\r\n", [
    'From: ' . $SUBJECT . ' <' . $FROM . '>',
    'Content-Type: text/plain; charset=utf-8',
    'Content-Transfer-Encoding: 8bit',
    'MIME-Version: 1.0',
]);

$encodedSubject = '=?UTF-8?B?' . base64_encode($SUBJECT . ' — ' . $name) . '?=';

if (!mail($TO, $encodedSubject, $body, $headers)) {
    http_response_code(500);
    exit('Письмо не ушло');
}

echo 'ok';
