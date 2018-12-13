<?php
/**
 * Created by PhpStorm.
 * User: ema
 * Date: 30.11.2017
 * Time: 14:08
 */

namespace app\components\log;

use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

/**
 * Класс для вывода сообщений лога в консоль
 * @package app\components\log
 */
class ConsoleTarget extends Target
{
    /**
     * @var string Формат отступов во вложеных блоках
     */
    public $offset_str = "|";
    /**
     * @var string Формат префикса каждой строке
     */
    public $prefix_template = '%level% %time%';
    /**
     * @var int Формат длинны заголовка сообщения
     */
    public $label_length_buffer = 12;
    /**
     * @var string Формат разделителя заголовка от тела сообщения
     */
    public $label_delimiter = ": ";

    /**
     * @var array Краткие обозначения важности сообщения
     */
    public $error_levels = [
        Logger::LEVEL_ERROR         => 'E',
        Logger::LEVEL_WARNING       => 'W',
        Logger::LEVEL_INFO          => 'I',
        Logger::LEVEL_TRACE         => 'T',
        Logger::LEVEL_PROFILE_BEGIN => 'PB',
        Logger::LEVEL_PROFILE_END   => 'PE',
        Logger::LEVEL_PROFILE       => 'P',
    ];

    /**
     * @var int Счётчик уровня вложености
     */
    protected $offset_count = 0;

    /**
     *  Метод добавляет отступ для всех следующих сообщений для создания ощущения вложености
     */
    public function down($prefix)
    {
        $this->print("<!------------------------------------------------------------------------", $prefix);
        $this->offset_count++;
    }

    /**
     *  Метод вызываемый Логером для вывода лога
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            $this->formatMessage($message);
        }
    }

    /**
     * Метод для фильтрации сообщения для этого Таргета
     * Улучшена возможность указывать более точные фильтры
     * Пример:
     *  Заголовок a/b/c
     *  Не выводить a/b*
     *  Выводить a/b/c*
     * Такое сообщение будет выведено т.к. у разрешение на вывод более точно (больше длинна)
     * @param array $messages
     * @param int $levels
     * @param array $categories
     * @param array $except
     * @return array
     */
    public static function filterMessages($messages, $levels = 0, $categories = [], $except = [])
    {
        foreach ($messages as $i => $message) {
            if ($levels && !($levels & $message[1])) {
                unset($messages[$i]);
                continue;
            }

            $matched = empty($categories);
            foreach ($categories as $category) {
                if (
                    $message[2] === $category ||
                    (!empty($category) &&
                        substr_compare($category, '*', -1, 1) === 0 &&
                        strpos($message[2], rtrim($category, '*')) === 0)
                ) {
                    foreach ($except as $ignore_category) {
                        if ($message[2] === $ignore_category ||
                            (\strlen($category) < \strlen($ignore_category) &&
                                !empty($ignore_category) &&
                                substr_compare($ignore_category, '*', -1, 1) === 0 &&
                                strpos($message[2], rtrim($ignore_category, '*')) === 0)) {
                            continue 2;
                        }
                    }
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }


    /**
     * Метод форматиравания сообщения
     * @param array $message Сообщение в формате (Тело, Важность, Заголовок, Время, Трасировка, Используемая память)
     */
    public function formatMessage($message)
    {
        list($value, $level_num, $label, $timestamp, $trace, $memory_usage) = $message;

        $prefix = $this->getMessagePrefix($message);
        $level = $this->getLevelName($level_num);
        $dt = \DateTime::createFromFormat("U.u", $timestamp);
        if ($dt) {
            $time = $dt->format('H:i:s.u');
        } else {
            $time = $timestamp;
        }

        $replace_keys = [
            '%prefix%',
            '%level%',
            '%time%',
        ];
        $replace_value = [
            $prefix,
            $level,
            $time,
        ];
        $prefix = \str_pad(\str_replace($replace_keys, $replace_value, $this->prefix_template), 18, " ");

        if (\is_string($value)) {
            if (\substr($value, 0, 2) == ">>") {
                $this->down($prefix);
                $value = \substr($value, 2);
                if ($value == '') {
                    return;
                }
            } elseif (\substr($value, 0, 2) == "<<") {
                $value = \substr($value, 2);
                $this->formatMessage([$value, $level_num, $label, $timestamp, $trace, $memory_usage]);
                $this->up($prefix);
                return;
            }
        }
        $text = $this->valueToString($value);
        $text = \sprintf("%-" . $this->label_length_buffer . "s%s%s", $label, $this->label_delimiter, $text);
        $this->print($text, $prefix);
    }

    /**
     * Метод для получения (и вывода контекста)
     * В консольном приложении он мне не нужен, по этому сделал заглушку
     * @return string
     */
    public function getContextMessage()
    {
        return '';
    }

    /**
     * Метод для получения сокращения по коду важности сообщения
     * @param $level int Код важности сообщения
     * @return mixed|string Сокращение
     */
    protected function getLevelName($level)
    {
        return isset($this->error_levels[$level]) ? $this->error_levels[$level] : 'UNKNOWN';
    }

    /**
     * Метод позволяющей ввыодить в консоль информацию о ходе выполнения импорта
     * @param string $name Имя парамется для вывода в консоль
     * @param mixed $value Данные для вывода (Строка, Массив, Булево)
     */
    public function print($text, $prefix = '')
    {

        $first_offset = \str_pad('', $this->offset_count * \strlen($this->offset_str), $this->offset_str);
        $text = \str_replace("\n", "\n" . $prefix . $first_offset, $text);
        printf("%s%s%s\n", $prefix, $first_offset, $text);
        //Console::ansiFormat();
    }

    /**
     *  Метод уменьшает отступ для всех следующих сообщений
     */
    public function up($prefix)
    {
        $this->offset_count--;
        $this->print("------------------------------------------------------------------------!>", $prefix);
    }

    /**
     * Метод для приведения Тела сообщения в читаемыый(текстовый) вид
     * @param $value mixed
     * @return string
     */
    public function valueToString($value)
    {
        if (!is_string($value)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($value instanceof \Throwable || $value instanceof \Exception) {
                $value = (string)$value;
            } else {
                $value = VarDumper::export($value);
            }
        }
        return $value;
    }
}