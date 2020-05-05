<?php

require_once('simplevk-master/autoload.php'); // БЛИБЛИОТЕКИ
require './vendor/autoload.php';// БЛИБЛИОТЕКИ

use Krugozor\Database\Mysql\Mysql as Mysql; // КЛАССЫ ДЛЯ РАБОТЫ С БД
use DigitalStar\vk_api\vk_api; // Основной класс
use DigitalStar\vk_api\Message; // Конструктор сообщений
use DigitalStar\vk_api\VkApiException; // Обработка ошибок

$host = 'localhost'; // По умолчанию localhost или ваш IP адрес сервера
$name = ''; // логин для авторизации к БД
$pass = ''; // Пароль для авторизации к БД
$bdname = ''; // ИМЯ базы данных
$vk_key = ''; // Длинный ключ сообщества, который мы получим чуть позже
$confirm = ''; // СТРОКА которую должен вернуть сервер
$v = '5.103'; // Версия API, последняя на сегодняшнее число, оставлять таким если на новых работать в будущем не будет

$db = Mysql::create($host, $name, $pass)->setDatabaseName($bdname)->setCharset('utf8mb4');
$vk = vk_api::create($vk_key, $v)->setConfirm($confirm);
$my_msg = new Message($vk);
$data = json_decode(file_get_contents('php://input')); //Получает и декодирует JSON пришедший из ВК

$vk->sendOK();
//$vk->debug();

// ТУТ УЖЕ БУДЕМ ПИСАТЬ КОД //

// Переменные для удобной работы в будущем
$id = $data->object->message->from_id; // ИД того кто написал
$peer_id = $data->object->message->peer_id; // Только для бесед (ид беседы)

$time = time();
$cmd = explode(" ", mb_strtolower($data->object->message->text)); // Команды
$message = $data->object->message->text; // Сообщение полученное ботом
$new_ids = current($data->object->message->fwd_messages)->from_id ?? $data->object->message->reply_message->from_id; // ИД того чье сообщение переслали
$userinfo = $vk->userInfo($id);
$bonus = $vk->buttonText('⏰ Бонус!', 'green', ['command' => 'bonus']);
// Закончили с переменными
if ($id < 0){exit();} // ПРОВЕРЯЕМ что сообщение прислал юзер а не сообщество

if ($data->type == 'message_new') {
    if (isset($data->object->message->payload)) {  //получаем payload
        $payload = json_decode($data->object->message->payload, True); // Декодируем кнопки в массив
    } else {
        $payload = null; // Если пришел пустой массив кнопок, то присваеваем кнопке NULL
    }
    $payload = $payload['command'];

    $id_reg_check = $db->query('SELECT vk_id FROM users WHERE vk_id = ?i', $id)->fetch_assoc()['vk_id']; // Пытаемся получить пользователя который написал сообщение боту
    if (!$id_reg_check and $id > 0) { // Если вдруг запрос вернул NULL (0) это FALSE, то используя знак ! перед переменной, все начинаем работать наоборот, FALSE становится TRUE
        // Так же мы проверяем что $id больше нуля, что бы не отвечать другим ботам, но лучше в самом верху добавить такую проверку что бы не делать лашних обращений к БД!
        $db->query("INSERT INTO users (vk_id, nick, status, time) VALUES (?i, '?s', ?i, ?i)", $id, "$userinfo[first_name] $userinfo[last_name]", 0, $time);
        $vk->sendButton($peer_id, "Приветствую  тебя, @id$id ($userinfo[first_name] $userinfo[last_name]), ты теперь один из нас, вступай в ряды мощных панамеровцев!", [[$bonus]]);
    }



    // ТУТ будут наши команды

    if ($cmd[0] == 'казино'){ // Первая команда

        if (!$cmd[1]){ // если вторая команда пустая она вернет FALSE
            $vk->sendMessage($peer_id, 'Вы не указали ставку!');
        }elseif ($cmd[1] == 'все' or $cmd[1] == 'всё'){ // Если указано все

            $balance = $db->query('SELECT balance FROM users WHERE vk_id = ?i', $id)->fetch_assoc()['balance']; // вытягиваем весь баланс

            if($balance == 0) {
                $vk->sendMessage($peer_id, 'У Вас нет денег :(');
            } else {
                $result = mt_rand(1, 4); // 1 - проиграл половину, 2 - победа x1.5, 3 - победа x2, 4 - проиграл все
                $win_money = ($result == 1 ? $balance / 2 : ($result == 2 ? $balance * 1.5 : ($result == 3 ? $balance * 2 : 0)));
                $win_nowin = ($result == 1 ? 'проиграли половину' : ($result == 2 ? 'выиграли x1.5' : ($result == 3 ? 'выиграли x2' : 'проиграли все')));
                $vk->sendMessage($peer_id, "Вы $win_nowin, ваш баланс теперь составляет $win_money монет.");
                $db->query('UPDATE users SET balance = ?i WHERE vk_id = ?i', $win_money, $id); // Обновляем данные
            }
        } else {

         $sum =  str_replace(['к','k'], '000', $cmd[1]); // наши Кk превращаем в человеческий вид, заменяя их на нули :)
         $sum =  ltrim(mb_eregi_replace('[^0-9]', '', $sum),'0'); // удаляем лишние символы, лишние нули спереди и все что может поломать систему :), подробнее о функциях можно почитать в интернете
         $balance = $db->query('SELECT balance FROM users WHERE vk_id = ?i', $id)->fetch_assoc()['balance']; // вытягиваем весь баланс

            if($balance < $sum) {
                $vk->sendMessage($peer_id, 'У вас не достаточно денег');
            } else {
            $result = mt_rand(1, 4); // 1 - проиграл половину, 2 - победа x1.5, 3 - победа x2, 4 - проиграл все

            $win_money = ($result == 1 ?  $balance - ($sum / 2)  : ($result == 2 ? $balance + ($sum * 1.5) : ($result == 3 ? $balance + ($sum * 2) : $balance - $sum)));
            $win_nowin = ($result == 1 ? 'проиграли половину' : ($result == 2 ? 'выиграли x1.5' : ($result == 3 ? 'выиграли x2' : 'проиграли все')));

            $vk->sendMessage($peer_id, "Вы $win_nowin, ваш баланс теперь составляет $win_money монет.");
            $db->query('UPDATE users SET balance =  ?i WHERE vk_id = ?i',  $win_money, $id); // Обновляем данные
            }
        }


    }


    // Давайте для обработки кнопки воспльзуемся SWITCH - CASE
    switch ($payload) // Проще говоря мы загрузили кнопки кнопки в свич, теперь проверяем что за кнопка была нажата и обрабатываем ее
    {
        case 'bonus';
        $time_bonus = $id_reg_check = $db->query('SELECT time_bonus FROM users WHERE vk_id = ?i', $id)->fetch_assoc()['time_bonus'];
        if ($time_bonus < $time){
            //  + 21600 минут = 6 часов
            $next_bonus = $time + 21600; // Прибавляем 6 часов для следующего бонуса!
            $rand_money = mt_rand(100, 5000); // Рандомно выбираем число от 100 до 5000, используя встроенную функцию PHP mt_rand
            $db->query('UPDATE users SET time_bonus = ?i, balance = balance + ?i WHERE vk_id = ?i',$next_bonus, $rand_money, $id); // Обновляем данные
            $vk->sendMessage($peer_id, "Вы взяли бонус, Вам выпало $rand_money монет");
        } else { // Иначе сообщим о том что бонус уже взят!

            $next_bonus = date("d.m в H:i:s",$time_bonus);
            $vk->sendMessage($peer_id,"Вы уже брали бонус ранее, следующий будет доступен \"$next_bonus\"");
        }

        break;

    }


}
