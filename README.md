# Модуль «Экспорт РИНЦ XML»

Модуль (плагин) для OJS, позволяющий экспортировать метаданные статей в формате РИНЦ XML.

## Требования

  - OJS 3.x
  - PHP 7.x

## Установка

Скачайте архив [.tar.gz](https://github.com/erikrause/RSCIExportOJSPlugin/releases/) и установите через менеджер модулей (plugins) в OJS.

## Использование

Зайдите в "Инструменты"->"Импорт/Экспорт", найдите модуль «Экспорт РИНЦ XML». Настройте модуль. На вкладке "Выпуски" выберите выпуск для экспорта. 
Начентся загрузка архива с содержанием выпуска (гранки и обложка) и XML-файлом с метаданными статей. В системе articulus в проекте выпуска журнала зайдите во влкадку "Восстановить" и загрузите скачанный архив с метаданными.

### Ограничения
Этот модуль формирует все метаданные для РИНЦ, которые определены в базовой установке OJS 3.

Не формируются:
  - полнотекстовые индексы статьей
  - сквозной номер выпуска
  - рецензии статей
  - финансирование статей
  - рубрики статей
  - РИНЦ ID и коды авторов, кроме ORCID

### Обработка аффилиации авторов
При обработке аффилиации авторов можно после наименовании организации через запятую указать город и страну, модуль автоматически разделит организацию и адрес на разные атрибуты.
Также можно использовать символ ";" как разделитель между элементами массива, таким образом можно обозначить несколько организаций, причем при указании нескольких одинаковых адресов
сформируется один адрес.

Напрмер, атрибут "место работы" в OJS:
 > СурГУ, г. Сургут, Россия; ФГУ ФНЦ НИИСИ РАН, г. Сургут, Россия
сформирует следующие XML-тэги:

``` 
<orgName>СурГУ; ФГУ ФНЦ НИИСИ РАН</orgName>
<address>г. Сургут, Россия</address>
```
